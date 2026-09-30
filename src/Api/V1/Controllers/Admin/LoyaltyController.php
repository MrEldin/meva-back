<?php

namespace Meva\Api\V1\Controllers\Admin;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Meva\Api\V1\Controllers\Controller;
use Meva\Api\V1\Transformers\Commerce\OrderTransformer;
use Meva\Entities\Loyalty\Models\LoyaltyCoupon;
use Meva\Entities\Loyalty\Models\LoyaltyEntry;
use Meva\Entities\Loyalty\Services\LoyaltyService;

/**
 * Meva Klub at a glance, for the marketing desk: how many members, how many
 * points are out there, what the coupons have cost, and who the best
 * customers are.
 */
class LoyaltyController extends Controller
{
    public function overview(LoyaltyService $loyalty)
    {
        $issued = (int) LoyaltyEntry::query()->where('points', '>', 0)->sum('points');
        $redeemed = -(int) LoyaltyEntry::query()->where('reason', LoyaltyEntry::REDEEM)->sum('points');

        // What coupons actually took off orders: never more than the goods,
        // so the order's sub-total caps each one.
        $discount = LoyaltyCoupon::query()
            ->whereNotNull('loyalty_coupons.used_at')
            ->leftJoin('lunar_orders', 'lunar_orders.id', '=', 'loyalty_coupons.order_id')
            ->get(['loyalty_coupons.value', 'lunar_orders.sub_total'])
            ->sum(fn ($c): int => $c->sub_total === null ? (int) $c->value : min((int) $c->value, (int) $c->sub_total));

        $top = DB::table('loyalty_entries as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->groupBy('e.user_id', 'u.first_name', 'u.last_name', 'u.email')
            ->selectRaw('e.user_id, u.first_name, u.last_name, u.email, sum(e.points) as points')
            ->selectRaw("sum(case when e.reason <> 'redeem' then e.points else 0 end) as lifetime")
            ->orderByDesc('points')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'name' => trim($row->first_name.' '.$row->last_name),
                'email' => $row->email,
                'points' => (int) $row->points,
                'tier' => $loyalty->tierFor(max(0, (int) $row->lifetime))['key'],
            ]);

        return $this->response->array([
            'data' => [
                'members' => (int) LoyaltyEntry::query()->distinct()->count('user_id'),
                'points_outstanding' => (int) LoyaltyEntry::query()->sum('points'),
                'points_issued' => $issued,
                'points_redeemed' => $redeemed,
                'coupons_active' => LoyaltyCoupon::query()->active()->count(),
                'coupons_used' => LoyaltyCoupon::query()->whereNotNull('used_at')->count(),
                'discount_given' => $discount,
                'discount_given_formatted' => OrderTransformer::money($discount),
                'top' => $top->all(),
            ],
        ])->setStatusCode(Response::HTTP_OK);
    }
}
