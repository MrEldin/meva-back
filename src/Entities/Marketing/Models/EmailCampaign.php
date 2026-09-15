<?php

namespace Meva\Entities\Marketing\Models;

use Illuminate\Database\Eloquent\Model;
use Meva\Entities\User\Models\User;

/**
 * One e-mail campaign: what it says, who it goes to, and whether it has gone.
 */
class EmailCampaign extends Model
{
    protected $table = 'email_campaigns';

    protected $fillable = [
        'name', 'template', 'subject', 'preheader', 'audience', 'blocks', 'status', 'sent_at', 'recipients', 'created_by',
    ];

    protected $casts = [
        'blocks' => 'array',
        'sent_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
