<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent', 'occurred_at'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'occurred_at' => 'datetime'];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
