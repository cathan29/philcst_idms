<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Enums\IdIssuanceType;
use App\Models\AuditLog;
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

    public const PHYSICALLY_ISSUED = ['printed', 'ready_for_release', 'released', 'revoked'];

    public function createRequest(
        Student $student,
        StudentEnrollment $enrollment,
        IdIssuanceType $type,
        User $actor,
        ?string $notes = null,
        ?int $replacesCardId = null,
        array $auditContext = [],
    ): IdCard {
        return DB::transaction(function () use ($student, $enrollment, $type, $actor, $notes, $replacesCardId, $auditContext) {
            $lockedStudent = Student::lockForUpdate()->findOrFail($student->id);
            $lockedEnrollment = StudentEnrollment::where('student_id', $lockedStudent->id)
                ->lockForUpdate()
                ->find($enrollment->id);

            if (! $lockedEnrollment) {
                throw new DomainException('The selected enrollment does not belong to this student.');
            }

            if (IdCard::where('student_id', $lockedStudent->id)->whereIn('status', self::ACTIVE_PRODUCTION)->exists()) {
                throw new DomainException('This student already has an active ID production request.');
            }

            $previousCard = null;
            if ($type->requiresPreviousCard()) {
                if (! $replacesCardId) {
                    throw new DomainException('Select the existing physical ID associated with this replacement request.');
                }

                $previousCard = IdCard::where('student_id', $lockedStudent->id)
                    ->whereIn('status', ['released', 'revoked'])
                    ->lockForUpdate()
                    ->find($replacesCardId);

                if (! $previousCard) {
                    throw new DomainException('The selected existing ID is not eligible for this replacement request.');
                }
            } elseif ($replacesCardId) {
                throw new DomainException('An existing ID reference is only valid for a replacement-related request.');
            }

            if ($type === IdIssuanceType::Initial
                && IdCard::where('student_id', $lockedStudent->id)->whereIn('status', self::PHYSICALLY_ISSUED)->exists()) {
                throw new DomainException('A physical ID has already been produced for this student. Use an authorized reissuance reason.');
            }

            if ($type === IdIssuanceType::Other && blank($notes)) {
                throw new DomainException('Explain the other authorized reason before creating the request.');
            }

            $sequence = (int) IdCard::where('student_id', $lockedStudent->id)->max('replacement_sequence');
            if ($type !== IdIssuanceType::Initial) {
                $sequence++;
            }

            $card = IdCard::create([
                'student_id' => $lockedStudent->id,
                'replaces_card_id' => $previousCard?->id,
                'academic_year_id' => $lockedEnrollment->academic_year_id,
                'academic_term_id' => $lockedEnrollment->academic_term_id,
                'semester' => $lockedEnrollment->academicTerm?->name ?? $lockedEnrollment->semester,
                'card_token' => (string) Str::uuid(),
                'issuance_type' => $type->value,
                'replacement_sequence' => $sequence,
                'status' => CardStatus::Pending,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'internal_notes' => $notes,
            ]);

            IdStatusHistory::create([
                'id_card_id' => $card->id,
                'previous_status' => null,
                'new_status' => CardStatus::Pending,
                'user_id' => $actor->id,
                'reason' => 'ID production request created',
                'metadata' => [
                    'issuance_type' => $type->value,
                    'academic_term_id' => $lockedEnrollment->academic_term_id,
                    'student_enrollment_id' => $lockedEnrollment->id,
                    'replaces_card_id' => $previousCard?->id,
                ],
                'occurred_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'event' => 'id_production_request.created',
                'auditable_type' => IdCard::class,
                'auditable_id' => $card->id,
                'old_values' => null,
                'new_values' => [
                    'student_id' => $lockedStudent->id,
                    'student_enrollment_id' => $lockedEnrollment->id,
                    'academic_term_id' => $lockedEnrollment->academic_term_id,
                    'issuance_type' => $type->value,
                    'replaces_card_id' => $previousCard?->id,
                    'status' => CardStatus::Pending->value,
                ],
                'ip_address' => $auditContext['ip_address'] ?? null,
                'user_agent' => Str::limit($auditContext['user_agent'] ?? '', 1000, ''),
                'occurred_at' => now(),
            ]);

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

        return compact('eligible', 'ineligible', 'errors');
    }
}
