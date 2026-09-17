<?php

namespace Meva\Entities\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;
use Meva\Entities\Catalogue\Models\Review;

/**
 * Everything the shop can be searched for, in Meilisearch.
 *
 * The catalogue is sixty-eight products, so this is not about scale -- it is
 * about the search being any good. Matching `attribute_data like %term%` in
 * Postgres cannot forgive a typo, does not know that "seboreja" and
 * "seboreju" are the same word to a customer, and cannot rank a product
 * called "Šampon za kosu" above one that merely mentions shampoo in its
 * fourth paragraph. Meilisearch does all three, and it answers in one round
 * trip across four indexes at once.
 *
 * Four indexes rather than one, because the drop-down groups what it finds:
 * products, the shelves they sit on, what customers wrote, and the pages that
 * answer a question. A single index with a "type" field would rank them
 * against each other, and a review would push a product out of the list.
 *
 * Rebuilding is cheap enough that it is done whole rather than incrementally:
 * a couple of hundred documents, a second or two.
 */
class SearchIndex
{
    public const PRODUCTS = 'products';

    public const COLLECTIONS = 'collections';

    public const REVIEWS = 'reviews';

    public const PAGES = 'pages';

    public const INDEXES = [self::PRODUCTS, self::COLLECTIONS, self::REVIEWS, self::PAGES];

    public function __construct(protected Client $client) {}

    /**
     * Whether search is configured and answering.
     */
    public static function available(): bool
    {
        return config('search.enabled') && filled(config('search.key'));
    }

    /**
     * Rebuild every index from the database.
     *
     * @return array<string, int> index name => documents written
     */
    public function rebuild(): array
    {
        $written = [];

        foreach ([
            self::PRODUCTS => $this->products(),
            self::COLLECTIONS => $this->collections(),
            self::REVIEWS => $this->reviews(),
            self::PAGES => $this->pages(),
        ] as $name => $documents) {
            $this->configure($name);

            $index = $this->client->index($name);
            $index->deleteAllDocuments();

            if ($documents !== []) {
                $index->addDocuments($documents, 'id');
            }

            $written[$name] = count($documents);
        }

        return $written;
    }

    /**
     * Search every index at once and keep them apart in the answer.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function search(string $term): array
    {
        $limits = config('search.limits');

        // The client calls toArray() on each of these, so they have to be
        // SearchQuery objects; an array of arrays fatals, and a fatal here
        // looks exactly like "Meilisearch found nothing".
        $queries = collect(self::INDEXES)
            ->map(fn (string $name): SearchQuery => (new SearchQuery)
                ->setIndexUid($name)
                ->setQuery($term)
                ->setLimit($limits[$name] ?? 5))
            ->all();

        $results = $this->client->multiSearch($queries);

        return collect($results['results'] ?? [])
            ->mapWithKeys(fn (array $result): array => [
                $result['indexUid'] => $result['hits'] ?? [],
            ])
            ->all();
    }

    /**
     * What each index searches, and in what order it prefers a match.
     *
     * The order of `searchableAttributes` is the order of importance: a word
     * in a product's name outranks the same word in the middle of its
     * description, which is what someone typing "seboreja" expects.
     */
    protected function configure(string $name): void
    {
        $settings = match ($name) {
            self::PRODUCTS => [
                'searchableAttributes' => ['name', 'categories', 'summary', 'text'],
                'sortableAttributes' => ['rank'],
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness', 'rank:asc'],
            ],
            self::COLLECTIONS => [
                'searchableAttributes' => ['name', 'products'],
            ],
            self::REVIEWS => [
                'searchableAttributes' => ['body', 'product_name', 'name'],
            ],
            self::PAGES => [
                'searchableAttributes' => ['title', 'question', 'answer', 'page'],
            ],
        };

        // Serbian is written in both alphabets and typed without diacritics as
        // often as with them; Meilisearch normalises those away by default.
        $this->client->index($name)->updateSettings($settings + [
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8],
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function products(): array
    {
        return Product::query()
            ->where('status', 'published')
            ->with(['variants.prices.currency', 'collections', 'productType', 'media'])
            ->get()
            ->map(function (Product $product): array {
                $variant = $product->variants->first();
                $price = $variant?->prices->first(fn ($p): bool => $p->currency?->code === 'RSD');

                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->attribute_data?->get('name'),
                    'slug' => (string) $product->attribute_data?->get('slug'),
                    'summary' => $this->plain((string) $product->attribute_data?->get('short_description'), 220),
                    'text' => $this->plain((string) $product->attribute_data?->get('description'), 1200),
                    'categories' => $product->collections
                        ->map(fn ($c): string => (string) $c->attribute_data?->get('name'))
                        ->values()
                        ->all(),
                    'is_set' => $product->productType?->name === 'Set',
                    'price' => $price ? number_format($price->price->decimal(), 0, ',', '.').' RSD' : null,
                    'image' => $this->cutout($product),
                    // Best sellers first when two products match equally well.
                    'rank' => $this->rankFor($product),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collections(): array
    {
        return LunarCollection::query()
            ->withCount(['products as products_count' => fn ($q) => $q->where('status', 'published')])
            ->with(['products' => fn ($q) => $q->where('status', 'published')])
            ->get()
            ->filter(fn (LunarCollection $c): bool => $c->products_count > 0)
            ->map(fn (LunarCollection $c): array => [
                'id' => (int) $c->id,
                'name' => (string) $c->attribute_data?->get('name'),
                'slug' => (string) $c->attribute_data?->get('slug'),
                'count' => (int) $c->products_count,
                // So "krema" also finds the shelf the creams are on.
                'products' => $c->products
                    ->map(fn ($p): string => (string) $p->attribute_data?->get('name'))
                    ->values()
                    ->all(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function reviews(): array
    {
        return Review::query()
            ->published()
            ->with('product')
            ->get()
            ->map(fn (Review $review): array => [
                'id' => (int) $review->id,
                'name' => (string) $review->name,
                'body' => (string) $review->body,
                'rating' => (int) $review->rating,
                'product_name' => $review->product
                    ? (string) $review->product->attribute_data?->get('name')
                    : $review->product_label,
                'product_slug' => $review->product
                    ? (string) $review->product->attribute_data?->get('slug')
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * The reading pages, so a question typed into the search box finds its
     * answer instead of nothing.
     *
     * These live in the storefront rather than the database, so they are
     * written out here; the wording is the wording on those pages.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function pages(): array
    {
        return collect(SearchablePages::all())
            ->map(fn (array $page, int $i): array => $page + ['id' => $i + 1])
            ->values()
            ->all();
    }

    /**
     * Where a product sits in the order history, as a tie-breaker.
     */
    protected function rankFor(Product $product): int
    {
        static $sold = null;

        if ($sold === null) {
            $sold = DB::table('archive_order_items')
                ->whereNotNull('product_id')
                ->select('product_id', DB::raw('sum(quantity) as sold'))
                ->groupBy('product_id')
                ->pluck('sold', 'product_id');
        }

        // Meilisearch sorts ascending, so more sold must be a smaller number.
        return 1000000 - (int) ($sold[$product->id] ?? 0);
    }

    /**
     * The product's transparent picture, for the drop-down.
     */
    protected function cutout(Product $product): ?string
    {
        $media = $product->getMedia('images')
            ->first(fn ($item): bool => (bool) $item->getCustomProperty('cutout'))
            ?? $product->getMedia('images')->first();

        if ($media === null) {
            return null;
        }

        return $media->hasGeneratedConversion('cutout-sm')
            ? $media->getFullUrl('cutout-sm')
            : $media->getFullUrl();
    }

    /**
     * HTML out, entities decoded, whitespace squashed, cut to length.
     */
    protected function plain(string $html, int $limit): string
    {
        return Str::limit(Str::squish(html_entity_decode(strip_tags($html))), $limit, '');
    }
}
