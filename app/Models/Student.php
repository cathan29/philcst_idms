<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'status', 'record_source'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function idCards(): HasMany
    {
        return $this->hasMany(IdCard::class);
    }

    public function latestCard(): HasOne
    {
        return $this->hasOne(IdCard::class)->latestOfMany();
    }

    public function latestEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)->latestOfMany();
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter(fn ($part) => filled($part))->implode(' ');
    }
}
