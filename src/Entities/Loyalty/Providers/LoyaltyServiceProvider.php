<?php

namespace Meva\Entities\Loyalty\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Order;
use Meva\Entities\Loyalty\Services\LoyaltyService;

/**
 * Wires Meva Klub into the order book.
 */
class LoyaltyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoyaltyService::class);
    }

    public function boot(): void
    {
        // Points follow an order's status wherever it is changed from -- the
        // back office today, a courier webhook tomorrow -- rather than being
        // remembered by each place that moves an order along.
        Order::updated(function (Order $order): void {
            if ($order->wasChanged('status')) {
                app(LoyaltyService::class)->syncOrder($order);
            }
        });
    }
}
