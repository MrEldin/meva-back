<?php

namespace Meva\Api\V1\Controllers\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Product;
use Meva\Api\V1\Controllers\Controller;
use Meva\Entities\Search\SearchIndex;
use Throwable;

/**
 * One search box, four kinds of answer.
 *
 * Someone typing "seboreja" may be after a preparation, the shelf all of them
 * sit on, what another customer said about one, or the answer to a question
 * about it. The drop-down shows all four, in that order, rather than making
 * them guess which one the box is for.
 *
 * If Meilisearch is unreachable the shop still answers -- product names
 * matched in the database, the way it did before -- because a search box that
 * returns nothing looks like a shop with nothing in it.
 */
class SearchController extends Controller
{
    public function __construct(protected SearchIndex $index) {}

    public function __invoke(Request $request)
    {
        $term = trim((string) $request->input('q', ''));

        if (mb_strlen($term) < 2) {
            return $this->answer([], $term, false);
        }

        if (SearchIndex::available()) {
            try {
                return $this->answer($this->index->search($term), $term, true);
            } catch (Throwable $e) {
                Log::warning('Search fell back to the database: '.$e->getMessage());
            }
        }

        return $this->answer(['products' => $this->fromDatabase($term)], $term, false);
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     */
    protected function answer(array $groups, string $term, bool $indexed)
    {
        $total = collect($groups)->sum(fn (array $hits): int => count($hits));

        return $this->response->array([
            'data' => [
                'products' => array_values($groups[SearchIndex::PRODUCTS] ?? []),
                'collections' => array_values($groups[SearchIndex::COLLECTIONS] ?? []),
                'reviews' => array_values($groups[SearchIndex::REVIEWS] ?? []),
                'pages' => array_values($groups[SearchIndex::PAGES] ?? []),
            ],
            'meta' => [
                'query' => $term,
                'total' => $total,
                'indexed' => $indexed,
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * The old way, kept for when the index is not there.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fromDatabase(string $term): array
    {
        return Product::query()
            ->where('status', 'published')
            ->where('attribute_data', 'like', '%'.$term.'%')
            ->with(['variants.prices.currency', 'media'])
            ->limit(config('search.limits.products', 6))
            ->get()
            ->map(function (Product $product): array {
                $variant = $product->variants->first();
                $price = $variant?->prices->first(fn ($p): bool => $p->currency?->code === 'RSD');
                $media = $product->getMedia('images')
                    ->first(fn ($item): bool => (bool) $item->getCustomProperty('cutout'))
                    ?? $product->getMedia('images')->first();

                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->attribute_data?->get('name'),
                    'slug' => (string) $product->attribute_data?->get('slug'),
                    'summary' => null,
                    'price' => $price ? number_format($price->price->decimal(), 0, ',', '.').' RSD' : null,
                    'image' => $media?->hasGeneratedConversion('cutout-sm')
                        ? $media->getFullUrl('cutout-sm')
                        : $media?->getFullUrl(),
                ];
            })
            ->values()
            ->all();
    }
}
