<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdStatusHistory extends Model
{
    protected $fillable = ['id_card_id', 'previous_status', 'new_status', 'user_id', 'scanner_device_id', 'reason', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['previous_status' => CardStatus::class, 'new_status' => CardStatus::class, 'metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(IdCard::class, 'id_card_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
