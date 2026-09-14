<?php

namespace Meva\Entities\Archive\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order from the old WooCommerce shop, kept for reporting.
 *
 * Money is in minor units, as everywhere else in this application.
 */
class ArchiveOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'total' => 'integer',
            'goods' => 'integer',
            'tax' => 'integer',
            'shipping' => 'integer',
            'discount' => 'integer',
            'session_pages' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ArchiveCustomer::class, 'archive_customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ArchiveOrderItem::class);
    }

    /**
     * Scope to orders placed within the given range.
     */
    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('ordered_at', [$from, $to]);
    }

    /**
     * Scope to the orders that actually earned money.
     *
     * The old shop never closed orders out, so everything sits at "processing";
     * only the cancelled ones should be excluded from revenue.
     */
    public function scopeRevenue(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }
}
