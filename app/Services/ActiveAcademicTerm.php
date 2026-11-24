<?php

namespace App\Services;

use App\Models\AcademicTerm;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActiveAcademicTerm
{
    private const CACHE_KEY = 'idms.active-academic-term-id';

    public function current(): ?AcademicTerm
    {
        $id = Cache::get(self::CACHE_KEY);
        if ($id !== null) {
            $cached = AcademicTerm::with('academicYear')->whereKey($id)->where('status', 'active')->first();
            if ($cached) {
                return $cached;
            }
            Cache::forget(self::CACHE_KEY);
        }

        $term = AcademicTerm::with('academicYear')->where('status', 'active')->latest('updated_at')->latest('id')->first();
        if ($term) {
            Cache::forever(self::CACHE_KEY, $term->id);
        }

        return $term;
    }

    public function activate(AcademicTerm $term): AcademicTerm
    {
        $active = DB::transaction(function () use ($term): AcademicTerm {
            AcademicTerm::query()->lockForUpdate()->where('status', 'active')->whereKeyNot($term->id)->update(['status' => 'closed']);
            $term->update(['status' => 'active']);

            return $term->fresh(['academicYear']);
        });

        Cache::forever(self::CACHE_KEY, $active->id);

        return $active;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}