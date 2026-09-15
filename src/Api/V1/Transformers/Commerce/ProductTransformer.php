<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Lunar\Models\Product;
use Meva\Entities\Catalogue\Money;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

class ProductTransformer extends TransformerAbstract
{
    /**
     * Resources that can be included if requested.
     */
    protected array $availableIncludes = ['variants'];

    /**
     * Transform a product.
     *
     * Lunar keeps the editorial fields in attribute_data as field-type objects,
     * so they are flattened here rather than leaking Lunar's internals to the
     * client.
     */
    public function transform(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'status' => $product->status,
            'product_type_id' => (int) $product->product_type_id,
            'brand' => $product->brand?->name,
            'name' => $this->attribute($product, 'name'),
            'slug' => $this->attribute($product, 'slug'),
            'description' => $this->attribute($product, 'description'),
            'short_description' => $this->attribute($product, 'short_description'),
            'price' => $this->price($product),
            'image' => $product->getFirstMediaUrl('images') ?: null,
            'sku' => $product->variants->first()?->sku,
            'created_at' => $product->created_at?->toAtomString(),
            'updated_at' => $product->updated_at?->toAtomString(),
        ];
    }

    /**
     * The product's price in dinars, as the editor types it.
     */
    protected function price(Product $product): ?float
    {
        $price = $product->variants->first()?->prices
            ->first(fn ($p): bool => $p->currency?->code === 'RSD')
            ?? $product->variants->first()?->prices->first();

        $minor = Money::minor($price);

        return $minor === null ? null : round($minor / 100, 2);
    }

    /**
     * Include the product's variants.
     */
    public function includeVariants(Product $product)
    {
        return $this->collection($product->variants, new ProductVariantTransformer, false);
    }

    /**
     * Read a single attribute value as a plain string.
     */
    protected function attribute(Product $product, string $handle): ?string
    {
        $value = $product->attribute_data?->get($handle);

        return $value === null ? null : (string) $value;
    }
}
