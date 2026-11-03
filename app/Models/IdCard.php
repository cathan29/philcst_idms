<?php

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdCard extends Model
{
    protected $fillable = ['student_id', 'academic_year_id', 'academic_term_id', 'card_token', 'external_identifier', 'semester', 'issuance_type', 'replacement_sequence', 'status', 'requested_by', 'assigned_staff_id', 'requested_at', 'printing_started_at', 'printed_at', 'printed_by', 'ready_at', 'released_at', 'released_by', 'hold_previous_status', 'hold_reason', 'held_at', 'held_by', 'hold_resolved_at', 'hold_resolved_by', 'internal_notes'];

    protected function casts(): array
    {
        return ['status' => CardStatus::class, 'requested_at' => 'datetime', 'printing_started_at' => 'datetime', 'printed_at' => 'datetime', 'ready_at' => 'datetime', 'released_at' => 'datetime', 'held_at' => 'datetime', 'hold_resolved_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(IdStatusHistory::class);
    }
}
