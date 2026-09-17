<?php

namespace Meva\Entities\Catalogue\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Product;

/**
 * Something a customer wrote about a preparation.
 *
 * A review may name a product the shop no longer sells under that name, so
 * the link to the catalogue is optional and the wording it arrived with is
 * kept beside it.
 */
class Review extends Model
{
    protected $table = 'reviews';

    protected $fillable = [
        'name', 'product_id', 'product_label', 'rating', 'body', 'source', 'published_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'published_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Only what has been published, newest first, and the ones with a product first. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
