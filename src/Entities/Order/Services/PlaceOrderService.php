<?php

namespace Meva\Entities\Order\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
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
                'customer_reference' => $data['customer']['email'] ?? null,
                'notes' => $data['customer']['note'] ?? null,
            ]);

            return $order->refresh();
        });
    }

    /**
     * Attach the shipping option.
     *
     * Delivery has always been free, but Lunar refuses to create an order
     * without an option on the cart, so one is set explicitly rather than left
     * to a manifest the storefront never queries.
     */
    protected function addShipping(Cart $cart): void
    {
        $cart->setShippingOption(new ShippingOption(
            name: 'Besplatna dostava',
            description: 'Isporuka kurirskom službom, 2-3 radna dana.',
            identifier: 'FREE',
            price: new Price(0, $cart->currency, 1),
            taxClass: TaxClass::getDefault() ?? TaxClass::firstOrFail(),
        ));
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
