<?php

namespace Meva\Entities\Archive\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product;

/**
 * A single line on an archived order.
 *
 * product_id is resolved against the imported catalogue where the product still
 * exists, so historical sales can be reported per live product; it is null for
 * products that were deleted before the export.
 */
class ArchiveOrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'total' => 'integer',
            'total_before_discount' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ArchiveOrder::class, 'archive_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
