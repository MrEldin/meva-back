<?php

namespace Meva\Entities\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Someone who asked to hear from the shop.
 */
class Subscriber extends Model
{
    protected $table = 'subscribers';

    protected $fillable = [
        'email', 'name', 'source', 'utm_source', 'utm_campaign', 'confirmed_at', 'unsubscribed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('unsubscribed_at');
    }
}
