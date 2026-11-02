<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdHolder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id_number',
        'first_name',
        'last_name',
        'holder_type',
        'department',
        'email',
        'phone',
        'status',
    ];

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
