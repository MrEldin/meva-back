<?php

namespace Meva\Entities\Order\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Meva\Entities\Order\Modifiers\FreeShippingModifier;

/**
 * Wires the shop's ordering rules into Lunar.
 */
class OrderServiceProvider extends ServiceProvider
{
    public function boot(ShippingModifiers $shippingModifiers): void
    {
        $shippingModifiers->add(FreeShippingModifier::class);
    }
}
