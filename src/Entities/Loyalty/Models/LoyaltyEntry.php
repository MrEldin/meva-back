<?php

namespace Meva\Entities\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Lunar\Models\Order;
use Meva\Entities\User\Models\User;

/**
 * One line of a member's points ledger: points given (positive) or taken
 * (negative), and why.
 */
class LoyaltyEntry extends Model
{
    public const WELCOME = 'welcome';
    public const ORDER = 'order';
    public const REDEEM = 'redeem';
    public const REVERSAL = 'reversal';
    public const ADJUSTMENT = 'adjustment';

    protected $table = 'loyalty_entries';

    protected $fillable = ['user_id', 'points', 'reason', 'order_id', 'coupon_id', 'note'];

    protected $casts = [
        'points' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function coupon()
    {
        return $this->belongsTo(LoyaltyCoupon::class, 'coupon_id');
    }
}
