<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Lunar\Models\Product;
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
            'description' => $this->attribute($product, 'description'),
            'created_at' => $product->created_at?->toAtomString(),
            'updated_at' => $product->updated_at?->toAtomString(),
        ];
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
