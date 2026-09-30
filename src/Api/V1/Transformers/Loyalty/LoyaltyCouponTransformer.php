<?php

namespace Meva\Api\V1\Transformers\Loyalty;

use Meva\Api\V1\Transformers\Commerce\OrderTransformer;
use Meva\Entities\Loyalty\Models\LoyaltyCoupon;
use PHPOpenSourceSaver\Fractal\TransformerAbstract;

/**
 * A Meva Klub coupon as the member sees it.
 */
class LoyaltyCouponTransformer extends TransformerAbstract
{
    public function transform(LoyaltyCoupon $coupon): array
    {
        return [
            'code' => $coupon->code,
            'value' => (int) $coupon->value,
            'value_formatted' => OrderTransformer::money((int) $coupon->value),
            'status' => $coupon->status(),
            'expires_at' => $coupon->expires_at?->toIso8601String(),
            'used_at' => $coupon->used_at?->toIso8601String(),
            'created_at' => $coupon->created_at?->toIso8601String(),
        ];
    }
}
