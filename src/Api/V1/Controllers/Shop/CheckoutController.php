<?php

namespace Meva\Api\V1\Controllers\Shop;

use Illuminate\Http\Response;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Requests\Order\OrderCreateRequest;
use Meva\Api\V1\Transformers\Commerce\OrderTransformer;
use Meva\Entities\Loyalty\Services\LoyaltyService;
use Meva\Entities\Order\Services\PlaceOrderService;

/**
 * Checkout. Cash on delivery, so placing the order is the whole flow.
 */
class CheckoutController extends Controller
{
    /**
     * Place an order.
     */
    public function store(OrderCreateRequest $request, PlaceOrderService $orders, LoyaltyService $loyalty)
    {
        $order = $orders->handle($request->validated());
        $total = OrderTransformer::minor($order->total);

        return $this->response->array([
            'data' => [
                'reference' => $order->reference,
                'total' => $order->total->value,
                'total_formatted' => number_format($order->total->decimal(), 0, ',', '.').' RSD',
                'placed_at' => $order->placed_at?->toAtomString(),
                'discount_total' => OrderTransformer::minor($order->discount_total),
                // What delivery will earn a member at their tier today; a
                // guest earns nothing.
                'points_estimate' => $loyalty->estimate(auth()->user(), $total),
            ],
        ])->setStatusCode(Response::HTTP_CREATED);
    }
}
