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
        // The original photographs are 4440px square and over two megabytes; a
        // messenger gives up long before one arrives. The "share" conversion is
        // a JPEG whatever the original was, which matters because Lunar's other
        // sizes keep the original format and some of this catalogue is PNG at
        // over half a megabyte.
        $image = $product->getFirstMediaUrl('images', 'share')
            ?: ($product->getFirstMediaUrl('images', 'large')
                ?: ($product->getFirstMediaUrl('images') ?: $this->storefront().'/og-image.jpg'));
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
     * The pages that answer what people ask before they order.
     *
     * Rendered here as well as in the storefront so a link to them survives
     * being sent to someone, and so a search engine can read them -- otherwise
     * nginx hands a crawler to this application and it answers 404, which is
     * what happened the day they were added.
     */
    public function page(string $key)
    {
        $pages = [
            'prica' => [
                'title' => 'Naša priča — Meva Kozmetika',
                'description' => 'Kako nastaje Meva: ručno rađeni preparati iz Novog Pazara od 2010, sastav koji se čita i šta rade na koži glave.',
                'social' => 'Ručno rađeno u Novom Pazaru od 2010. Sastav koji možete pročitati.',
                'path' => '/prica',
                'heading' => 'Naša priča',
                'body' => 'Meva nastaje u Novom Pazaru od 2010. Kuvamo u malim serijama, rukom, i svaka tegla nosi datum kad je napravljena. Sastav je ispisan punim imenom, bez sulfata i bez parabena, a preparati su ispitani u Institutu za javno zdravlje Vojvodine i u Superlabu.',
            ],
            'cesta-pitanja' => [
                'title' => 'Česta pitanja — Meva Kozmetika',
                'description' => 'Odgovori na pitanja o preparatima Meva Kozmetike, poručivanju, dostavi i upotrebi.',
                'social' => 'Koliko traje pakovanje, kada se vide rezultati, kako se poručuje.',
                'path' => '/cesta-pitanja',
                'heading' => 'Česta pitanja',
                'body' => 'Koliko traje jedno pakovanje, kada se vide prvi rezultati, može li uz terapiju koju je propisao lekar, i kako se poručuje bez otvaranja naloga.',
            ],
            'dostava' => [
                'title' => 'Dostava — Meva Kozmetika',
                'description' => 'Besplatna dostava u celoj Srbiji, isporuka za jedan do tri radna dana, plaćanje pouzećem kuriru.',
                'social' => 'Besplatno u celoj Srbiji, 1–3 radna dana, plaćate kuriru.',
                'path' => '/dostava',
                'heading' => 'Dostava',
                'body' => 'Dostava je besplatna u celoj Srbiji, bez minimalnog iznosa porudžbine. Paket stiže za jedan do tri radna dana, a plaćate kuriru kad stigne.',
            ],
            'reklamacije' => [
                'title' => 'Povrat i reklamacije — Meva Kozmetika',
                'description' => 'Rok od 14 dana za odustajanje od kupovine, postupak reklamacije i vraćanje novca.',
                'social' => '14 dana za odustajanje. Ako nešto nije u redu, šaljemo zamenu o našem trošku.',
                'path' => '/reklamacije',
                'heading' => 'Povrat i reklamacije',
                'body' => 'Imate 14 dana da odustanete od kupovine, bez objašnjenja. Ako je proizvod stigao oštećen ili pogrešan, šaljemo novi o našem trošku, a novac vraćamo u roku od 14 dana od prijema robe.',
            ],
        ];

        $page = $pages[$key] ?? abort(404);

        return view('share.page', [
            'title' => $page['title'],
            'description' => $page['description'],
            'social' => $page['social'],
            'url' => $this->storefront().$page['path'],
            'image' => $this->storefront().'/og-image.jpg',
            'type' => 'website',
            'body' => ['name' => $page['heading'], 'text' => $page['body']],
            'schema' => $this->organisationSchema(),
        ]);
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
