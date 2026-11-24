<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IdReportQuery
{
    public function query(array $filters, bool $latest = true): Builder
    {
        $query = IdCard::query()
            ->with([
                'student.enrollments.program',
                'student.enrollments.academicTerm.academicYear',
                'academicTerm.academicYear',
                'printedBy',
                'releasedBy',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $value): void {
                $escaped = addcslashes($value, '%_\\');
                $query->where(function (Builder $query) use ($value, $escaped): void {
                    $query->where('card_token', $value)
                        ->orWhere('external_identifier', $value)
                        ->orWhereHas('student', function (Builder $student) use ($escaped): void {
                            $student->where('student_number', 'like', "%{$escaped}%")
                                ->orWhere('first_name', 'like', "%{$escaped}%")
                                ->orWhere('last_name', 'like', "%{$escaped}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['academic_term'] ?? null, fn (Builder $query, int $value) => $query->where('academic_term_id', $value))
            ->when($filters['academic_year'] ?? null, fn (Builder $query, int $value) => $query->whereHas('academicTerm', fn (Builder $term) => $term->where('academic_year_id', $value)))
            ->when($filters['semester'] ?? null, fn (Builder $query, string $value) => $query->whereHas('academicTerm', fn (Builder $term) => $term->where('code', $value)))
            ->when(
                ($filters['program'] ?? null) || ($filters['year_level'] ?? null),
                fn (Builder $query) => $query->whereHas('student.enrollments', function (Builder $enrollments) use ($filters): void {
                    $enrollments
                        ->when($filters['program'] ?? null, fn (Builder $query, int $value) => $query->where('program_id', $value))
                        ->when($filters['year_level'] ?? null, fn (Builder $query, string $value) => $query->where('year_level', $value))
                        ->when($filters['academic_term'] ?? null, fn (Builder $query, int $value) => $query->where('academic_term_id', $value))
                        ->when($filters['academic_year'] ?? null, fn (Builder $query, int $value) => $query->where('academic_year_id', $value));
                }),
            )
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('requested_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('requested_at', '<=', $value));

        $this->applyReportType($query, $filters['report_type'] ?? 'all');

        return $query->when(
            $latest,
            fn (Builder $query) => $query->latest('requested_at')->latest('id'),
            fn (Builder $query) => $query->orderBy('id'),
        );
    }

    public function summary(array $filters): array
    {
        $base = $this->query($filters, false);
        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (clone $base)->count(),
            'production' => $byStatus->only(['pending', 'for_printing', 'on_hold'])->sum(),
            'printed' => (clone $base)->whereNotNull('printed_at')->count(),
            'released' => (int) ($byStatus['released'] ?? 0),
            'unreleased' => (clone $base)->where('status', '!=', CardStatus::Released->value)->count(),
        ];
    }

    public function enrollmentFor(IdCard $card): ?StudentEnrollment
    {
        return $card->academic_term_id
            ? $card->student->enrollments->firstWhere('academic_term_id', $card->academic_term_id)
            : $card->student->enrollments->sortByDesc('id')->first();
    }

    public function rows(Collection $cards): Collection
    {
        return $cards->map(fn (IdCard $card) => [
            'card' => $card,
            'enrollment' => $this->enrollmentFor($card),
            'discrepancy' => $this->hasDiscrepancy($card),
        ]);
    }

    public function hasDiscrepancy(IdCard $card): bool
    {
        $printedStates = ['printed', 'ready_for_release', 'released'];

        return (in_array($card->status->value, $printedStates, true) && blank($card->printed_at))
            || (in_array($card->status->value, ['ready_for_release', 'released'], true) && blank($card->ready_at))
            || ($card->status === CardStatus::Released && (blank($card->released_at) || blank($card->released_by)))
            || (! in_array($card->status->value, $printedStates, true) && filled($card->printed_at))
            || blank($card->academic_term_id)
            || ($card->academic_term_id && ! $card->student->enrollments->contains('academic_term_id', $card->academic_term_id));
    }

    private function applyReportType(Builder $query, string $type): void
    {
        match ($type) {
            'production' => $query->whereIn('status', ['pending', 'for_printing', 'on_hold']),
            'printed' => $query->whereNotNull('printed_at'),
            'released' => $query->where('status', CardStatus::Released->value),
            'unreleased' => $query->where('status', '!=', CardStatus::Released->value),
            'reprint' => $query->where('issuance_type', '!=', 'initial'),
            'discrepancy' => $this->applyDiscrepancy($query),
            default => null,
        };
    }

    private function applyDiscrepancy(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query
                ->where(fn (Builder $part) => $part->whereIn('status', ['printed', 'ready_for_release', 'released'])->whereNull('printed_at'))
                ->orWhere(fn (Builder $part) => $part->whereIn('status', ['ready_for_release', 'released'])->whereNull('ready_at'))
                ->orWhere(fn (Builder $part) => $part->where('status', 'released')->where(fn (Builder $release) => $release->whereNull('released_at')->orWhereNull('released_by')))
                ->orWhere(fn (Builder $part) => $part->whereNotIn('status', ['printed', 'ready_for_release', 'released'])->whereNotNull('printed_at'))
                ->orWhereNull('academic_term_id')
                ->orWhereDoesntHave('student.enrollments', fn (Builder $enrollment) => $enrollment->whereColumn('student_enrollments.academic_term_id', 'id_cards.academic_term_id'));
        });
    }
}
