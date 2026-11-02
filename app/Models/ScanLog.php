<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_holder_id',
        'scanned_code',
        'action',
        'status',
        'remarks',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function idHolder(): BelongsTo
    {
        return $this->belongsTo(IdHolder::class)->withTrashed();
    }
}
