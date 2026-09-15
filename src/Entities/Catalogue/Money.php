<?php

namespace Meva\Entities\Catalogue;

use Lunar\Models\Price;

/**
 * Reading Lunar prices without waking the rest of the model.
 *
 * Lunar casts a price row's amount into a Price object, and building one
 * touches the record the price belongs to -- so reading twenty five products'
 * prices fetched twenty five variants one at a time. The stored column is
 * already the amount in minor units, so it is read directly.
 */
class Money
{
    /**
     * A price row's amount, in minor units.
     */
    public static function minor(?Price $price): ?int
    {
        if ($price === null) {
            return null;
        }

        $raw = $price->getRawOriginal('price') ?? $price->getAttributes()['price'] ?? null;

        return $raw === null ? null : (int) $raw;
    }

    /**
     * The same amount as the shop writes it: 1.400 RSD.
     */
    public static function format(?int $minor, string $currency = 'RSD'): ?string
    {
        return $minor === null ? null : number_format($minor / 100, 0, ',', '.').' '.$currency;
    }
}
