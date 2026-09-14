<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Lunar\Models\ProductVariant;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

class ProductVariantTransformer extends TransformerAbstract
{
    /**
     * Resources that can be included if requested.
     */
    protected array $availableIncludes = ['prices'];

    /**
     * Transform a product variant.
     */
    public function transform(ProductVariant $variant): array
    {
        return [
            'id' => (int) $variant->id,
            'sku' => $variant->sku,
            'stock' => (int) $variant->stock,
            'purchasable' => $variant->purchasable,
            'shippable' => (bool) $variant->shippable,
        ];
    }

    /**
     * Include the variant's prices.
     */
    public function includePrices(ProductVariant $variant)
    {
        return $this->collection($variant->prices, new PriceTransformer, false);
    }
}
