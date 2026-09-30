<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Meva\Entities\Catalogue\Money;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

/**
 * A product as the storefront needs it: flattened, priced, and with its imagery
 * already resolved to URLs.
 */
class ShopProductTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['description', 'images', 'set'];

    /**
     * Transform a product for the storefront.
     */
    public function transform(Product $product): array
    {
        $variant = $product->variants->first();

        return [
            'id' => (int) $product->id,
            'sku' => $variant?->sku,
            'name' => (string) $product->attribute_data?->get('name'),
            'slug' => (string) $product->attribute_data?->get('slug'),
            'excerpt' => strip_tags((string) $product->attribute_data?->get('short_description')),
            'is_set' => $product->productType?->name === 'Set',
            'price' => $this->price($variant, 'RSD'),
            'price_eur' => $this->price($variant, 'EUR'),
            'image' => $this->primaryImage($product),
            'has_cutout' => $this->cutout($product) !== null,
            'categories' => $product->collections->map(fn ($c): array => [
                'name' => (string) $c->attribute_data?->get('name'),
                'slug' => (string) $c->attribute_data?->get('slug'),
            ])->values(),
        ];
    }

    /**
     * Include the full description, which the listing does not need.
     */
    public function includeDescription(Product $product)
    {
        return $this->primitive([
            'html' => (string) $product->attribute_data?->get('description'),
            // Each empty when the product has none: a set often has no
            // ingredients of its own, and the page then shows no such section.
            'ingredients' => (string) $product->attribute_data?->get('ingredients'),
            'usage' => (string) $product->attribute_data?->get('usage'),
        ]);
    }

    /**
     * Include every photograph.
     */
    public function includeImages(Product $product)
    {
        return $this->primitive(
            $product->getMedia('images')
                ->map(fn ($media): array => [
                    'url' => $this->url($media, 'cutout'),
                    'full' => $media->getFullUrl(),
                    'cutout' => (bool) $media->getCustomProperty('cutout'),
                    'primary' => (bool) $media->getCustomProperty('primary'),
                ])
                ->sortByDesc('cutout')
                ->values()
                ->all()
        );
    }

    /**
     * Include what a set is made of: each part with its own picture, price
     * and address, so the page can show the set as the shelf it is and say
     * what the parts would cost one by one.
     */
    public function includeSet(Product $product)
    {
        $rows = \Illuminate\Support\Facades\DB::table('product_bundle_items')
            ->where('bundle_product_id', $product->id)
            ->orderBy('id')
            ->get()
            ->keyBy('item_product_id');

        if ($rows->isEmpty()) {
            return $this->primitive([]);
        }

        $parts = Product::query()
            ->with(['variants.prices.currency', 'media'])
            ->whereIn('id', $rows->keys())
            ->get()
            ->sortBy(fn (Product $p): int => (int) $rows[$p->id]->id)
            ->map(fn (Product $p): array => [
                'id' => (int) $p->id,
                'name' => (string) $p->attribute_data?->get('name'),
                'slug' => (string) $p->attribute_data?->get('slug'),
                'published' => $p->status === 'published',
                'price' => $this->price($p->variants->first(), 'RSD'),
                'image' => $this->primaryImage($p),
                'quantity' => (int) $rows[$p->id]->quantity,
            ])
            ->values();

        return $this->primitive($parts->all());
    }

    /**
     * The product's price in one currency, in both minor units and decimal.
     *
     * @return array{minor: int, amount: float, formatted: string}|null
     */
    protected function price(?ProductVariant $variant, string $currency): ?array
    {
        $price = $variant?->prices->first(
            fn (Price $price): bool => $price->currency?->code === $currency
        );

        if ($price === null) {
            return null;
        }

        return [
            'minor' => (int) Money::minor($price),
            'amount' => $price->price->decimal(),
            'formatted' => $this->format($price->price->decimal(), $currency),
        ];
    }

    /**
     * Format money the way the shop always has: 1.200 RSD, 14,00 EUR.
     */
    protected function format(float $amount, string $currency): string
    {
        return $currency === 'RSD'
            ? number_format($amount, 0, ',', '.').' RSD'
            : number_format($amount, 2, ',', '.').' €';
    }

    /**
     * The picture a listing leads with.
     *
     * The cutout wins when there is one: transparent, it can stand on a tinted
     * circle or in open white instead of sitting in a photographed box. Six
     * products have none, and they fall back to the studio photograph.
     */
    protected function primaryImage(Product $product): ?string
    {
        $cutout = $this->cutout($product);

        if ($cutout !== null) {
            return $this->url($cutout, 'cutout-sm');
        }

        $media = $product->getMedia('images');

        $primary = $media->first(fn ($item): bool => (bool) $item->getCustomProperty('primary'));

        return ($primary ?? $media->first())?->getFullUrl();
    }

    /**
     * The product's cutout, if one has been made for it.
     */
    protected function cutout(Product $product): ?\Spatie\MediaLibrary\MediaCollections\Models\Media
    {
        return $product->getMedia('images')
            ->first(fn ($media): bool => (bool) $media->getCustomProperty('cutout'));
    }

    /**
     * A conversion's URL, falling back to the original until the queue has
     * caught up with generating it.
     */
    protected function url(\Spatie\MediaLibrary\MediaCollections\Models\Media $media, string $conversion): string
    {
        return $media->hasGeneratedConversion($conversion)
            ? $media->getFullUrl($conversion)
            : $media->getFullUrl();
    }
}
