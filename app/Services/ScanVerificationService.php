<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\ScanLog;
use App\Models\Student;
use App\Models\User;

class ScanVerificationService
{
    public function __construct(
        private QrPayloadResolver $resolver,
        private ActiveAcademicTerm $activeTerms,
    ) {}

    public function verify(string $payload, string $mode, User $operator): array
    {
        $interpreted = $this->resolver->interpret($payload);
        if (! $interpreted) {
            return $this->record($payload, $mode, $operator, null, null, 'invalid', 'Invalid or unsupported identifier.');
        }
        $activeTerm = $this->activeTerms->current();
        if (! $activeTerm) {
            return $this->record($payload, $mode, $operator, null, null, 'no_active_term', 'No academic term is active. Ask an administrator to select one.');
        }

        $exactCard = IdCard::where('card_token', $interpreted['value'])->orWhere('external_identifier', $interpreted['value'])->first();
        if ($exactCard && $exactCard->academic_term_id !== $activeTerm->id) {
            $response = $this->record($payload, $mode, $operator, $exactCard->student, $exactCard, 'wrong_term', 'This physical card belongs to a different academic term. No action was taken.');
            $response['student'] = null;
            return $response;
        }

        $studentNumber = $exactCard?->student?->student_number ?? $interpreted['value'];
        $student = Student::query()
            ->with([
                'enrollments' => fn ($query) => $query->where('academic_term_id', $activeTerm->id)->with(['program', 'academicTerm.academicYear']),
                'idCards' => fn ($query) => $query->where('academic_term_id', $activeTerm->id)->with(['academicTerm.academicYear', 'histories.user', 'printedBy', 'releasedBy', 'release.releasedBy'])->latest('id'),
            ])
            ->where('student_number', $studentNumber)
            ->first();

        if (! $student) {
            return $this->record($payload, $mode, $operator, null, null, 'not_found', 'No matching student or card record was found.');
        }
        if ($student->enrollments->isEmpty()) {
            $response = $this->record($payload, $mode, $operator, $student, null, 'wrong_term', 'The student is not enrolled in the active academic term.');
            $response['student'] = null;
            return $response;
        }
        if ($student->idCards->isEmpty()) {
            return $this->record($payload, $mode, $operator, $student, null, 'no_card', 'The student is enrolled but has no ID card record for the active term.');
        }

        $card = $exactCard ? $student->idCards->firstWhere('id', $exactCard->id) : $this->selectOperationalCard($student, $mode);
        if (! $card) {
            return $this->record($payload, $mode, $operator, $student, null, 'ambiguous', 'More than one physical card could match this operation. Scan the exact card identifier.');
        }
        $enrollment = $student->enrollments->first();
        $status = $card->status;
        $message = match ($status) {
            CardStatus::Pending => 'This card record is pending production.',
            CardStatus::ForPrinting => 'This card is queued for authorized printing.',
            CardStatus::Printed => 'Printing has been confirmed for this physical card.',
            CardStatus::ReadyForRelease => 'This physical card is ready for authorized release.',
            CardStatus::Released => 'This physical card has already been released.',
            CardStatus::OnHold => 'This card record is on hold.',
            CardStatus::Revoked => 'This physical card is revoked and cannot be released.',
            CardStatus::Cancelled => 'This card record was cancelled.',
        };
        $result = $this->record($payload, $mode, $operator, $student, $card, $status->value, $message);
        $result['student'] = ['name' => $student->full_name, 'student_number' => $student->student_number];
        $result['enrollment'] = [
            'program' => $enrollment->program?->code,
            'year_level' => $enrollment->year_level,
            'term' => trim(collect([$enrollment->academicTerm?->academicYear?->name ? 'AY '.$enrollment->academicTerm->academicYear->name : null, $enrollment->academicTerm?->name])->filter()->implode(' - ')),
        ];
        $result['cards'] = $student->idCards->map(fn (IdCard $item) => $this->cardSummary($item))->values()->all();
        $result['card'] = $this->cardSummary($card);
        $result['lifecycle'] = [
            'printing_started_at' => $this->dateTime($card->printing_started_at),
            'printed_at' => $this->dateTime($card->printed_at),
            'printed_by' => $card->printedBy?->name,
            'ready_at' => $this->dateTime($card->ready_at),
            'released_at' => $this->dateTime($card->release?->released_at),
            'released_by' => $card->release?->releasedBy?->name,
        ];
        $result['history'] = $card->histories->sortByDesc('occurred_at')->take(10)->map(fn ($history) => [
            'previous_status' => $history->previous_status?->value,
            'new_status' => $history->new_status->value,
            'actor' => $history->user?->name ?? 'System',
            'reason' => $history->reason,
            'occurred_at' => $this->dateTime($history->occurred_at),
        ])->values()->all();
        $result['discrepancies'] = $this->discrepancies($card, $enrollment);

        return $result;
    }

    private function selectOperationalCard(Student $student, string $mode): ?IdCard
    {
        if ($mode === 'releasing') {
            $eligible = $student->idCards->where('status', CardStatus::ReadyForRelease);
            return $eligible->count() === 1 ? $eligible->first() : null;
        }
        if ($mode === 'production') {
            $eligible = $student->idCards->filter(fn (IdCard $card) => in_array($card->status, [CardStatus::ForPrinting, CardStatus::Printed], true));
            return $eligible->count() === 1 ? $eligible->first() : null;
        }
        return $student->idCards->first();
    }

    private function cardSummary(IdCard $card): array
    {
        return [
            'id' => $card->id,
            'status' => $card->status->value,
            'status_label' => strtoupper(str_replace('_', ' ', $card->status->value)),
            'issuance_type' => ucfirst($card->issuance_type),
            'last_updated' => $this->dateTime($card->histories->sortByDesc('occurred_at')->first()?->occurred_at ?? $card->updated_at),
            'printed_at' => $this->dateTime($card->printed_at),
            'released_at' => $this->dateTime($card->release?->released_at),
        ];
    }

    private function discrepancies(IdCard $card, mixed $enrollment): array
    {
        $issues = [];
        $printedStates = [CardStatus::Printed, CardStatus::ReadyForRelease, CardStatus::Released];
        if (in_array($card->status, $printedStates, true) && blank($card->printed_at)) $issues[] = 'Status indicates printing is complete, but no print timestamp exists.';
        if (in_array($card->status, [CardStatus::ReadyForRelease, CardStatus::Released], true) && blank($card->ready_at)) $issues[] = 'Release processing began without a ready-for-release timestamp.';
        if ($card->status === CardStatus::Released && ! $card->release) $issues[] = 'The card status says released, but no release transaction exists.';
        if (! in_array($card->status, $printedStates, true) && filled($card->printed_at)) $issues[] = 'A print timestamp exists before the workflow reached Printed.';
        if (! $enrollment) $issues[] = 'No active-term enrollment matches this card.';
        return $issues;
    }

    private function dateTime(mixed $value): ?string
    {
        return $value?->setTimezone(config('app.timezone'))->format('M j, Y g:i A');
    }

    private function record(string $payload, string $mode, User $operator, ?Student $student, ?IdCard $card, string $result, string $message): array
    {
        $hash = hash('sha256', trim($payload));
        $duplicate = ScanLog::where('operator_id', $operator->id)->where('payload_hash', $hash)->where('station_mode', $mode)->where('scanned_at', '>=', now()->subSeconds(2))->exists();
        $rejected = ['invalid', 'not_found', 'no_card', 'ambiguous', 'wrong_term', 'no_active_term'];
        ScanLog::create(['student_id' => $student?->id, 'id_card_id' => $card?->id, 'operator_id' => $operator->id, 'station_mode' => $mode, 'scanned_code' => substr($hash, 0, 12).'...', 'payload_hash' => $hash, 'action' => 'verify', 'status' => in_array($result, $rejected, true) ? 'rejected' : 'accepted', 'result' => $duplicate ? 'duplicate' : $result, 'remarks' => $duplicate ? 'Rapid duplicate scan recorded without changing status.' : $message, 'scanned_at' => now()]);
        return ['ok' => ! in_array($result, $rejected, true), 'result' => $duplicate ? 'duplicate' : $result, 'message' => $duplicate ? 'Duplicate scan recorded. No action was taken.' : $message, 'student' => $student ? ['name' => $student->full_name, 'student_number' => $student->student_number] : null, 'card' => null, 'cards' => [], 'enrollment' => null, 'lifecycle' => null, 'history' => [], 'discrepancies' => []];
    }
}