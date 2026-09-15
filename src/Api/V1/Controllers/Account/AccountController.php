<?php

namespace Meva\Api\V1\Controllers\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lunar\Models\Order;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Requests\User\RegisterRequest;
use Meva\Api\V1\Transformers\Commerce\OrderTransformer;
use Meva\Entities\User\Models\User;

/**
 * A customer's own corner of the shop: their account and their orders.
 */
class AccountController extends Controller
{
    /**
     * Open an account. Customers get the "customer" role and nothing more.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::query()->create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        $user->assignRole('customer');

        // Orders this person placed as a guest belong to them.
        Order::query()
            ->whereNull('user_id')
            ->where('customer_reference', $user->email)
            ->update(['user_id' => $user->id]);

        $token = auth()->login($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => array_merge($user->only(['id', 'first_name', 'last_name', 'email']), [
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => [],
            ]),
        ], Response::HTTP_CREATED);
    }

    /**
     * The signed-in customer's orders, newest first.
     */
    public function orders(Request $request)
    {
        $user = auth()->user();

        $orders = Order::query()
            ->whereNotNull('placed_at')
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('customer_reference', $user->email))
            ->with(['lines', 'shippingAddress'])
            ->latest('placed_at')
            ->paginate(min((int) $request->input('per_page', 20), 50));

        $transformer = new OrderTransformer;
        $transformer->setDefaultIncludes(['lines']);

        return $this->response
            ->paginator($orders, $transformer)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Follow an order without an account: its number and the e-mail it was
     * placed with, which only the person who ordered has.
     */
    public function track(Request $request)
    {
        $request->validate([
            'reference' => 'required|string|max:60',
            'email' => 'required|email',
        ]);

        $order = Order::query()
            ->whereNotNull('placed_at')
            ->where('reference', trim($request->input('reference')))
            ->whereRaw('lower(customer_reference) = ?', [mb_strtolower(trim($request->input('email')))])
            ->with(['lines', 'shippingAddress'])
            ->first();

        if ($order === null) {
            return response()->json([
                'message' => 'Ne možemo da pronađemo porudžbinu sa tim brojem i e-mailom.',
            ], Response::HTTP_NOT_FOUND);
        }

        $transformer = new OrderTransformer;
        $transformer->setDefaultIncludes(['lines', 'customer']);

        return $this->response
            ->item($order, $transformer)
            ->setStatusCode(Response::HTTP_OK);
    }
}
