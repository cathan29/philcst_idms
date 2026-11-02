<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdCard extends Model
{
    protected $fillable = ['student_id', 'academic_year_id', 'card_token', 'external_identifier', 'semester', 'issuance_type', 'replacement_sequence', 'status'];

    protected function casts(): array
    {
        return ['status' => CardStatus::class, 'printed_at' => 'datetime', 'ready_at' => 'datetime', 'released_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(IdStatusHistory::class);
    }
}
