<?php

namespace Meva\Entities\Order\Modifiers;

use Closure;
use Lunar\Base\ShippingModifier;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\TaxClass;

/**
 * Offers the house's one delivery option on every cart.
 *
 * Lunar resolves a cart's chosen shipping option out of the manifest rather
 * than off the address, so an option that is only set on the cart is invisible
 * when the order is created -- "Missing Shipping Option". Publishing it here,
 * as Lunar expects, makes it available to both the storefront and checkout.
 */
class FreeShippingModifier extends ShippingModifier
{
    public const IDENTIFIER = 'FREE';

    /**
     * Add the option just before the cart's totals are calculated.
     */
    public function handle(Cart $cart, Closure $next)
    {
        ShippingManifest::addOption(
            new ShippingOption(
                name: 'Besplatna dostava',
                description: 'Isporuka kurirskom službom, 2-3 radna dana.',
                identifier: self::IDENTIFIER,
                price: new Price(0, $cart->currency, 1),
                taxClass: TaxClass::getDefault() ?? TaxClass::first(),
            )
        );

        return $next($cart);
    }
}
