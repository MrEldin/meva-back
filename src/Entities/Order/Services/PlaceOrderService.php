<?php

namespace Meva\Entities\Order\Services;

use Illuminate\Support\Facades\DB;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Meva\Entities\Order\Modifiers\FreeShippingModifier;
use RuntimeException;

/**
 * Turns a storefront basket into a Lunar order.
 *
 * The shop is cash on delivery, so there is no payment step: the order is
 * created, and the courier collects. Everything runs in one transaction, since
 * a cart that half-becomes an order is worse than no order at all.
 */
class PlaceOrderService
{
    /**
     * @param  array{lines: array<int, array{sku: string, quantity: int}>, customer: array<string, mixed>}  $data
     */
    public function handle(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $cart = $this->buildCart($data['lines']);

            $this->addAddress($cart, $data['customer']);
            $this->addShipping($cart);

            $order = $cart->createOrder();

            // Nothing is ever "completed" until the courier reports back, but
            // the order must leave draft so it counts as a real sale.
            $order->update([
                'status' => 'awaiting-dispatch',
                'placed_at' => now(),
                // Stored lower-cased so an order can be found later whatever
                // way the customer typed their address.
                'customer_reference' => isset($data['customer']['email'])
                    ? mb_strtolower(trim($data['customer']['email']))
                    : null,
                'notes' => $data['customer']['note'] ?? null,
                // A signed-in customer sees the order in their account at once;
                // a guest's order is claimed when they register with the same
                // e-mail.
                'user_id' => auth()->id(),
            ]);

            return $order->refresh();
        });
    }

    /**
     * Attach the shipping option.
     *
     * Delivery has always been free. The option itself is published by
     * FreeShippingModifier, which is where Lunar looks for it when the order
     * is created; here it is simply chosen off the manifest.
     */
    protected function addShipping(Cart $cart): void
    {
        $option = collect(ShippingManifest::getOptions($cart))
            ->first(fn (ShippingOption $option): bool => $option->getIdentifier() === FreeShippingModifier::IDENTIFIER);

        if ($option === null) {
            throw new RuntimeException('Nijedna opcija dostave nije dostupna.');
        }

        $cart->setShippingOption($option);
    }

    /**
     * Build a cart holding the requested lines.
     *
     * @param  array<int, array{sku: string, quantity: int}>  $lines
     */
    protected function buildCart(array $lines): Cart
    {
        $cart = Cart::create([
            'currency_id' => Currency::getDefault()->id,
            'channel_id' => Channel::getDefault()->id,
        ]);

        foreach ($lines as $line) {
            $variant = ProductVariant::query()->where('sku', $line['sku'])->first();

            if ($variant === null) {
                throw new RuntimeException("Proizvod [{$line['sku']}] više nije dostupan.");
            }

            $cart->add($variant, max(1, (int) $line['quantity']));
        }

        return $cart->refresh()->calculate();
    }

    /**
     * Attach the delivery address.
     *
     * The same address bills and ships: there is nothing to bill, and asking a
     * customer for two addresses on a cash-on-delivery order is friction for
     * nothing.
     *
     * @param  array<string, mixed>  $customer
     */
    protected function addAddress(Cart $cart, array $customer): void
    {
        $address = [
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'line_one' => $customer['address'],
            'city' => $customer['city'],
            'postcode' => $customer['postcode'] ?? null,
            // Lunar requires a country on the address before it will create an
            // order; the shop ships within Serbia.
            'country_id' => Country::where('iso2', $customer['country'] ?? 'RS')->value('id'),
            'contact_email' => $customer['email'] ?? null,
            'contact_phone' => $customer['phone'],
        ];

        $cart->setShippingAddress($address);
        $cart->setBillingAddress($address);
    }
}
