<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Lunar\Models\Price;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

class PriceTransformer extends TransformerAbstract
{
    /**
     * Transform a price.
     */
    public function transform(Price $price): array
    {
        return [
            'id' => (int) $price->id,
            'currency' => $price->currency?->code,
            // Lunar stores money in minor units; both are exposed so a client
            // never has to guess which one it is looking at.
            'amount_minor' => (int) $price->price->value,
            'amount' => $price->price->decimal(),
            'min_quantity' => (int) $price->min_quantity,
        ];
    }
}
