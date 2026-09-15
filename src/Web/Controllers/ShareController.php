<?php

namespace Meva\Web\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;

/**
 * What a link to the shop looks like when it is shared or crawled.
 *
 * Messengers and search engines do not run the storefront's JavaScript, so
 * nginx sends their requests here instead: the same page, rendered server-side
 * with the tags that make a WhatsApp card, a Facebook preview or a search
 * result worth clicking -- and with enough plain text for an assistant to
 * describe the product accurately.
 */
class ShareController extends Controller
{
    /**
     * The storefront's own address, which every canonical URL points at.
     */
    protected function storefront(): string
    {
        return rtrim(config('meva.storefront_url'), '/');
    }

    /**
     * The shop's front page.
     */
    public function home()
    {
        return view('share.page', [
            'title' => 'Meva Kozmetika — prirodna nega kože i kose',
            'description' => 'Ručno rađena prirodna kozmetika iz Novog Pazara od 2010. Preparati za seboreju, psorijazu, ekcem, akne i negu kose. Besplatna dostava, plaćanje pouzećem.',
            // Messengers cut after about two lines, so the card says the two
            // things a stranger weighs before clicking.
            'social' => 'Ručno rađeno u Novom Pazaru od 2010. Besplatna dostava, plaćate kuriru.',
            'url' => $this->storefront().'/',
            'image' => $this->storefront().'/og-image.jpg',
            'type' => 'website',
            'body' => null,
            'schema' => $this->organisationSchema(),
        ]);
    }

    /**
     * The catalogue, or one category of it.
     */
    public function catalog(Request $request)
    {
        $category = $request->query('kategorija');
        $collection = $category
            ? LunarCollection::query()->get()->first(fn ($c): bool => (string) $c->attribute_data?->get('slug') === $category)
            : null;

        $name = $collection ? (string) $collection->attribute_data?->get('name') : 'Svi preparati';
        $count = Product::query()->where('status', 'published')->count();

        return view('share.page', [
            'title' => $name.' — Meva Kozmetika',
            'description' => $collection
                ? "Preparati iz kategorije {$name} — ručno rađena prirodna kozmetika Meva. Besplatna dostava u celoj Srbiji."
                : "Svih {$count} preparata Meva Kozmetike: nega kože, kosa, seboreja, psorijaza, ekcem i akne. Besplatna dostava, plaćanje pouzećem.",
            'social' => $collection
                ? "Preparati za {$name}. Besplatna dostava, plaćate kuriru."
                : "Svih {$count} preparata. Besplatna dostava, plaćate kuriru.",
            'url' => $this->storefront().'/proizvodi'.($category ? '?kategorija='.$category : ''),
            'image' => $this->storefront().'/og-image.jpg',
            'type' => 'website',
            'body' => null,
            'schema' => $this->organisationSchema(),
        ]);
    }

    /**
     * One product: the card people actually send to each other.
     */
    public function product(string $slug)
    {
        $product = Product::query()
            ->with(['variants.prices', 'media', 'collections'])
            ->get()
            ->first(fn ($p): bool => (string) $p->attribute_data?->get('slug') === $slug);

        abort_if($product === null, 404);

        $name = (string) $product->attribute_data?->get('name');
        $description = trim(Str::of((string) $product->attribute_data?->get('description'))->stripTags()->squish());
        $short = trim(Str::of((string) $product->attribute_data?->get('short_description'))->stripTags()->squish());
        $variant = $product->variants->first();
        $price = $variant?->prices->firstWhere('currency.code', 'RSD') ?? $variant?->prices->first();
        // The original photographs are 4440px square and over two megabytes;
        // a messenger gives up long before one arrives. Lunar already keeps an
        // 800px version beside it, which is thirty kilobytes and plenty for a
        // card that is never shown wider than a phone.
        $image = $product->getFirstMediaUrl('images', 'large')
            ?: ($product->getFirstMediaUrl('images') ?: $this->storefront().'/og-image.jpg');
        $url = $this->storefront().'/proizvod/'.$slug;

        $summary = Str::limit($short !== '' ? $short : $description, 180);
        $amount = \Meva\Entities\Catalogue\Money::minor($price);

        return view('share.page', [
            'title' => $name.' — Meva Kozmetika',
            'description' => $summary.($amount ? ' · '.number_format($amount / 100, 0, ',', '.').' RSD · besplatna dostava' : ''),
            // One sentence and the price. The long description is for search.
            'social' => Str::limit($short !== '' ? $short : $description, 95)
                .($amount ? ' · '.number_format($amount / 100, 0, ',', '.').' RSD · besplatna dostava' : ''),
            'url' => $url,
            'image' => $image,
            'type' => 'product',
            'price' => $amount,
            'body' => [
                'name' => $name,
                'description' => $description,
                'ingredients' => $this->section($description, 'Sastav'),
                'usage' => $this->section($description, 'Način upotrebe'),
                'categories' => $product->collections->map(fn ($c): string => (string) $c->attribute_data?->get('name'))->all(),
            ],
            'schema' => $this->productSchema($name, $summary, $description, $image, $url, $amount, $product->status === 'published'),
        ]);
    }

    /**
     * Every page worth crawling.
     */
    public function sitemap()
    {
        $products = Product::query()
            ->where('status', 'published')
            ->get()
            ->map(fn ($p): array => [
                'loc' => $this->storefront().'/proizvod/'.(string) $p->attribute_data?->get('slug'),
                'lastmod' => $p->updated_at?->toAtomString(),
                'priority' => '0.8',
            ]);

        $collections = LunarCollection::query()
            ->get()
            ->map(fn ($c): array => [
                'loc' => $this->storefront().'/proizvodi?kategorija='.(string) $c->attribute_data?->get('slug'),
                'lastmod' => $c->updated_at?->toAtomString(),
                'priority' => '0.6',
            ]);

        $pages = collect([
            ['loc' => $this->storefront().'/', 'lastmod' => now()->toAtomString(), 'priority' => '1.0'],
            ['loc' => $this->storefront().'/proizvodi', 'lastmod' => now()->toAtomString(), 'priority' => '0.9'],
        ]);

        return response()
            ->view('share.sitemap', ['urls' => $pages->concat($collections)->concat($products)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * What crawlers may read. Assistants are welcome: being quoted accurately
     * is how a small shop gets recommended.
     */
    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /korpa',
            'Disallow: /porucivanje',
            'Disallow: /nalog',
            '',
            'Sitemap: '.$this->storefront().'/sitemap.xml',
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Pull a labelled section out of a product description.
     */
    protected function section(string $description, string $label): ?string
    {
        if (! preg_match('/'.preg_quote($label, '/').':\s*(.+?)(?=(Sastav:|Način upotrebe:|$))/isu', $description, $match)) {
            return null;
        }

        return trim($match[1]) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function organisationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => 'Meva Kozmetika',
            'description' => 'Ručno rađena prirodna kozmetika iz Novog Pazara, od 2010.',
            'url' => $this->storefront().'/',
            'logo' => $this->storefront().'/favicon.png',
            'image' => $this->storefront().'/og-image.jpg',
            'priceRange' => '400–6400 RSD',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Miloša Obilića 20',
                'addressLocality' => 'Novi Pazar',
                'postalCode' => '36300',
                'addressCountry' => 'RS',
            ],
            'sameAs' => ['https://www.instagram.com/meva.cosmetics/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function productSchema(string $name, string $summary, string $description, string $image, string $url, ?int $amount, bool $published): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => $summary !== '' ? $summary : $description,
            'image' => [$image],
            'url' => $url,
            'brand' => ['@type' => 'Brand', 'name' => 'Meva Kozmetika'],
            'countryOfOrigin' => 'RS',
        ];

        if ($amount !== null) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'RSD',
                'price' => number_format($amount / 100, 2, '.', ''),
                'availability' => $published ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'shippingDetails' => [
                    '@type' => 'OfferShippingDetails',
                    'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '0', 'currency' => 'RSD'],
                    'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'RS'],
                ],
            ];
        }

        return $schema;
    }
}
