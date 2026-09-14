<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Lunar\Models\Collection;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

class ShopCollectionTransformer extends TransformerAbstract
{
    /**
     * Transform a category for the storefront.
     */
    public function transform(Collection $collection): array
    {
        return [
            'id' => (int) $collection->id,
            'name' => (string) $collection->attribute_data?->get('name'),
            'slug' => (string) $collection->attribute_data?->get('slug'),
            'products_count' => (int) ($collection->products_count ?? 0),
        ];
    }
}
