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
                    'url' => $media->getFullUrl(),
                    'primary' => (bool) $media->getCustomProperty('primary'),
                ])
                ->sortByDesc('primary')
                ->values()
                ->all()
        );
    }

    /**
     * Include what a set is made of.
     */
    public function includeSet(Product $product)
    {
        $items = \Illuminate\Support\Facades\DB::table('product_bundle_items as b')
            ->join('lunar_products as p', 'p.id', '=', 'b.item_product_id')
            ->where('b.bundle_product_id', $product->id)
            ->select('p.id', 'p.attribute_data', 'b.quantity')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) (json_decode($row->attribute_data, true)['name']['value'] ?? ''),
                'quantity' => (int) $row->quantity,
            ]);

        return $this->primitive($items->all());
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
     * The main photograph, falling back to whatever exists.
     */
    protected function primaryImage(Product $product): ?string
    {
        $media = $product->getMedia('images');

        $primary = $media->first(fn ($item): bool => (bool) $item->getCustomProperty('primary'));

        return ($primary ?? $media->first())?->getFullUrl();
    }
}
