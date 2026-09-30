<?php

namespace Meva\Entities\Loyalty\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lunar\Models\Order;
use Meva\Entities\User\Models\User;

/**
 * A coupon bought with points: worth a fixed amount off one order, for one
 * member, until it expires.
 */
class LoyaltyCoupon extends Model
{
    protected $table = 'loyalty_coupons';

    protected $fillable = ['user_id', 'code', 'value', 'reward_key', 'expires_at', 'used_at', 'order_id'];

    protected $casts = [
        'value' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Neither used nor past its date.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    /**
     * active, used or expired -- a used coupon stays "used" after its date.
     */
    public function status(): string
    {
        if ($this->used_at !== null) {
            return 'used';
        }

        return $this->expires_at->isPast() ? 'expired' : 'active';
    }
}
