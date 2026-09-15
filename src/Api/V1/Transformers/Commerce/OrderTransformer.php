<?php

namespace Meva\Api\V1\Transformers\Commerce;

use Lunar\Models\Order;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

/**
 * An order as the back office and a customer's account need to read it.
 */
class OrderTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['lines', 'customer'];

    public function transform(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'reference' => $order->reference,
            'status' => $order->status,
            'status_label' => self::STATUSES[$order->status] ?? $order->status,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'currency' => $order->currency_code,
            'sub_total' => self::minor($order->sub_total),
            'shipping_total' => self::minor($order->shipping_total),
            'discount_total' => self::minor($order->discount_total),
            'total' => self::minor($order->total),
            'total_formatted' => self::money(self::minor($order->total)),
            'items' => (int) $order->lines->where('type', 'physical')->sum('quantity'),
            'notes' => $order->notes,
        ];
    }

    /**
     * The statuses an order moves through, in the words the shop uses.
     */
    public const STATUSES = [
        'awaiting-dispatch' => 'Za slanje',
        'dispatched' => 'Poslato',
        'delivered' => 'Isporučeno',
        'returned' => 'Vraćeno',
        'cancelled' => 'Otkazano',
        // What the old shop called them, so the history reads in one language.
        'processing' => 'Završeno (arhiva)',
        'completed' => 'Završeno (arhiva)',
        'refunded' => 'Refundirano',
        'failed' => 'Neuspelo',
        'on-hold' => 'Na čekanju',
        'pending' => 'Neplaćeno',
    ];

    /**
     * The statuses an order may actually be moved to. The archive's own are
     * history and never offered.
     */
    public const CHANGEABLE = ['awaiting-dispatch', 'dispatched', 'delivered', 'returned', 'cancelled'];

    public function includeLines(Order $order)
    {
        return $this->primitive(
            $order->lines
                ->where('type', 'physical')
                ->map(fn ($line): array => [
                    'description' => $line->description,
                    'identifier' => $line->identifier,
                    'quantity' => (int) $line->quantity,
                    'unit_price' => self::minor($line->unit_price),
                    'total' => self::minor($line->total),
                    'total_formatted' => self::money(self::minor($line->total)),
                ])
                ->values()
                ->all()
        );
    }

    public function includeCustomer(Order $order)
    {
        $address = $order->shippingAddress ?? $order->billingAddress;

        return $this->primitive([
            'name' => trim(($address?->first_name ?? '').' '.($address?->last_name ?? '')) ?: null,
            'email' => $order->customer_reference,
            'phone' => $address?->contact_phone,
            'address' => $address?->line_one,
            'city' => $address?->city,
            'postcode' => $address?->postcode,
            'user_id' => $order->user_id,
        ]);
    }

    /**
     * Lunar hands money back as a Price object on some columns and a plain
     * integer on others; both mean minor units.
     */
    public static function minor(mixed $value): int
    {
        return (int) ($value instanceof \Lunar\DataTypes\Price ? $value->value : $value);
    }

    /**
     * Minor units as the shop writes them: 1.400 RSD.
     */
    public static function money(int $minor): string
    {
        return number_format($minor / 100, 0, ',', '.').' RSD';
    }
}
