<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdRelease extends Model
{
    protected $fillable = [
        'id_card_id', 'released_by', 'released_at', 'acknowledged_at',
        'claimant_name', 'claimant_relationship', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(IdCard::class, 'id_card_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}