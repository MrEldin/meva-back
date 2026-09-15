<?php

namespace Meva\Entities\Marketing\Email;

use Illuminate\Support\Facades\Cache;
use Lunar\Models\Product;
use Meva\Entities\Catalogue\Money;

/**
 * Looks up the products a campaign refers to.
 *
 * Blocks store a slug rather than a copy of the product, so a campaign
 * re-rendered next month shows today's price and picture. The whole published
 * catalogue is small enough to hold in one cached map, which keeps rendering a
 * preview to a single query even when the message shows six products.
 */
class ProductResolver
{
    /** @var array<string, array<string, mixed>>|null */
    protected ?array $map = null;

    /**
     * One product, shaped for the renderer.
     *
     * @return array<string, mixed>|null
     */
    public function find(?string $slug): ?array
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return $this->all()[$slug] ?? null;
    }

    /**
     * Every published product, keyed by slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->map ??= Cache::remember('email:products', 600, function (): array {
            $shop = rtrim(config('meva.storefront_url', 'https://meva.life'), '/');

            return Product::query()
                ->where('status', 'published')
                ->with(['variants.prices', 'media'])
                ->get()
                ->mapWithKeys(function (Product $product) use ($shop): array {
                    $slug = (string) ($product->attribute_data?->get('slug') ?? '');

                    if ($slug === '') {
                        return [];
                    }

                    $minor = Money::minor($product->variants->first()?->prices->first());

                    return [$slug => [
                        'slug' => $slug,
                        'name' => (string) ($product->attribute_data?->get('name') ?? ''),
                        'short_description' => (string) ($product->attribute_data?->get('short_description') ?? ''),
                        'image' => $product->getFirstMediaUrl('images') ?: null,
                        'minor' => $minor,
                        'price' => Money::format($minor),
                        'url' => $shop.'/proizvod/'.$slug,
                    ]];
                })
                ->all();
        });
    }

    /**
     * The catalogue as the editor's product picker wants it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(): array
    {
        return array_values($this->all());
    }
}
