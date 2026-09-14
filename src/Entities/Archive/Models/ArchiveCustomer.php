<?php

namespace Meva\Entities\Archive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer of the old shop, aggregated by email.
 *
 * Read-only history. Contains personal data: treat access accordingly, and
 * honour deletion requests here as well as in the live shop.
 */
class ArchiveCustomer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
            'orders_count' => 'integer',
            'total_spent' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ArchiveOrder::class);
    }

    /**
     * Full name, for display.
     */
    public function name(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
