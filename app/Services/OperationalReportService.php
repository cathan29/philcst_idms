<?php

namespace App\Services;

use App\Models\IdCard;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;

class OperationalReportService
{
    public function summary(int $termId): array
    {
        $enrollments = $this->enrollments($termId);
        $printed = $this->printedCards($termId);
        $released = $this->releasedCards($termId);

        $enrolledStudents = (clone $enrollments)->distinct()->count('student_id');
        $uniquePrinted = (clone $printed)->distinct()->count('student_id');
        $uniqueReleased = (clone $released)->distinct()->count('student_id');

        return [
            'enrolled_students' => $enrolledStudents,
            'printed_cards' => (clone $printed)->count(),
            'released_cards' => (clone $released)->count(),
            'printed_not_released' => $this->printedNotReleased($termId)->count(),
            'unique_students_printed' => $uniquePrinted,
            'students_without_printed' => $enrolledStudents - $uniquePrinted,
            'unique_students_released' => $uniqueReleased,
            'students_without_released' => $enrolledStudents - $uniqueReleased,
        ];
    }

    public function enrollments(int $termId): Builder
    {
        return StudentEnrollment::query()
            ->with(['student', 'program', 'academicTerm.academicYear'])
            ->where('academic_term_id', $termId);
    }

    public function printedCards(int $termId): Builder
    {
        return IdCard::query()
            ->with(['student.enrollments.program', 'student.enrollments.academicTerm.academicYear', 'academicTerm.academicYear', 'release'])
            ->where('academic_term_id', $termId)
            ->whereNotNull('printed_at');
    }

    public function releasedCards(int $termId): Builder
    {
        return $this->printedCards($termId)->whereHas('release');
    }

    public function printedNotReleased(int $termId): Builder
    {
        return $this->printedCards($termId)->whereDoesntHave('release');
    }

    public function studentsWithoutPrinted(int $termId): Builder
    {
        return $this->enrollments($termId)->whereDoesntHave('student.idCards', fn (Builder $cards) => $cards
            ->where('academic_term_id', $termId)
            ->whereNotNull('printed_at'));
    }

    public function studentsWithoutReleased(int $termId): Builder
    {
        return $this->enrollments($termId)->whereDoesntHave('student.idCards', fn (Builder $cards) => $cards
            ->where('academic_term_id', $termId)
            ->whereHas('release'));
    }

    public function dataset(string $type, int $termId): Builder
    {
        return match ($type) {
            'printed_cards' => $this->printedCards($termId),
            'released_cards' => $this->releasedCards($termId),
            'printed_not_released' => $this->printedNotReleased($termId),
            'without_printed' => $this->studentsWithoutPrinted($termId),
            'without_released' => $this->studentsWithoutReleased($termId),
            default => $this->enrollments($termId),
        };
    }
}