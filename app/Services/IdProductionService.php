<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\IdStatusHistory;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdProductionService
{
    public const ACTIVE_PRODUCTION = ['pending', 'for_printing', 'printed', 'ready_for_release', 'on_hold'];

    public function createRequest(Student $student, ?StudentEnrollment $enrollment, string $type, User $actor, ?string $notes = null): IdCard
    {
        return DB::transaction(function () use ($student, $enrollment, $type, $actor, $notes) {
            $locked = Student::lockForUpdate()->findOrFail($student->id);
            if (IdCard::where('student_id', $locked->id)->whereIn('status', self::ACTIVE_PRODUCTION)->exists()) {
                throw new DomainException('This student already has an active ID production request.');
            }$hasValid = IdCard::where('student_id', $locked->id)->where('status', 'released')->exists();
            if ($hasValid && $type === 'initial') {
                throw new DomainException('This student already has a released ID. Select an authorized replacement reason.');
            }$sequence = (int) IdCard::where('student_id', $locked->id)->max('replacement_sequence');
            if ($type !== 'initial') {
                $sequence++;
            }$card = IdCard::create(['student_id' => $locked->id, 'academic_year_id' => $enrollment?->academic_year_id, 'academic_term_id' => $enrollment?->academic_term_id, 'semester' => $enrollment?->academicTerm?->name ?? $enrollment?->semester, 'card_token' => (string) Str::uuid(), 'issuance_type' => $type, 'replacement_sequence' => $sequence, 'status' => CardStatus::Pending, 'requested_by' => $actor->id, 'requested_at' => now(), 'internal_notes' => $notes]);
            IdStatusHistory::create(['id_card_id' => $card->id, 'previous_status' => null, 'new_status' => CardStatus::Pending, 'user_id' => $actor->id, 'reason' => 'ID production request created', 'metadata' => ['issuance_type' => $type, 'academic_term_id' => $enrollment?->academic_term_id], 'occurred_at' => now()]);

            return $card;
        }, 3);
    }

    public function bulk(array $ids, CardStatus $target, User $actor, ?string $reason = null): array
    {
        $eligible = 0;
        $ineligible = 0;
        $errors = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            try {
                $card = IdCard::findOrFail($id);
                app(CardStatusTransitionService::class)->transition($card, $target, $actor, $reason);
                $eligible++;
            } catch (\Throwable $e) {
                $ineligible++;
                $errors[$id] = $e->getMessage();
            }
        }

return compact('eligible','ineligible','errors');
    }
}
