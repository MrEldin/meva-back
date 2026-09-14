<?php

namespace Meva\Api\V1\Controllers\Shop;

use Illuminate\Http\Response;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Requests\Order\OrderCreateRequest;
use Meva\Entities\Order\Services\PlaceOrderService;

/**
 * Checkout. Cash on delivery, so placing the order is the whole flow.
 */
class CheckoutController extends Controller
{
    /**
     * Place an order.
     */
    public function store(OrderCreateRequest $request, PlaceOrderService $orders)
    {
        $order = $orders->handle($request->validated());

        return $this->response->array([
            'data' => [
                'reference' => $order->reference,
                'total' => $order->total->value,
                'total_formatted' => number_format($order->total->decimal(), 0, ',', '.').' RSD',
                'placed_at' => $order->placed_at?->toAtomString(),
            ],
        ])->setStatusCode(Response::HTTP_CREATED);
    }
}
