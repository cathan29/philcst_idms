<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\IdRelease;
use App\Models\IdStatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CardStatusTransitionService
{
    public function transition(
        IdCard $card,
        CardStatus $target,
        User $actor,
        ?string $reason = null,
        array $context = [],
    ): IdCard {
        return DB::transaction(function () use ($card, $target, $actor, $reason, $context) {
            $locked = IdCard::lockForUpdate()->findOrFail($card->id);
            $activeTerm = app(ActiveAcademicTerm::class)->current();
            if (! $activeTerm || $locked->academic_term_id !== $activeTerm->id) {
                throw new DomainException('Only a card from the active academic term can be updated.');
            }
            $current = $locked->status;

            if ($current === $target) {
                throw new DomainException('This production status is already confirmed.');
            }

            if (! $current->canTransitionTo($target)) {
                throw new DomainException("Cards cannot transition from {$current->value} to {$target->value}.");
            }

            if ($target === CardStatus::Released) {
                $activeTerm = app(ActiveAcademicTerm::class)->current();
                if (! $activeTerm || $locked->academic_term_id !== $activeTerm->id) {
                    throw new DomainException('Only a card from the active academic term can be released.');
                }
                if (IdRelease::where('id_card_id', $locked->id)->exists()) {
                    throw new DomainException('This physical card already has a release transaction.');
                }
            }

            if (in_array($target, [CardStatus::OnHold, CardStatus::Cancelled, CardStatus::Revoked], true) && blank($reason)) {
                throw new DomainException('A documented reason is required.');
            }

            if ($current === CardStatus::OnHold
                && ! in_array($target, [CardStatus::Cancelled, CardStatus::Revoked], true)
                && $locked->hold_previous_status !== $target->value) {
                throw new DomainException('An on-hold request may only resume its previous workflow state.');
            }

            $values = ['status' => $target];

            if ($target === CardStatus::ForPrinting && blank($locked->printing_started_at)) {
                $values += ['printing_started_at' => now(), 'assigned_staff_id' => $actor->id];
            }

            if ($target === CardStatus::Printed) {
                $values += ['printed_at' => now(), 'printed_by' => $actor->id];
            }

            if ($target === CardStatus::ReadyForRelease) {
                $values += ['ready_at' => now()];
            }

            if ($target === CardStatus::Released) {
                $values += [
                    'released_at' => now(),
                    'released_by' => $actor->id,
                    'release_acknowledged_at' => now(),
                    'claimant_name' => $context['claimant_name'] ?? null,
                    'claimant_relationship' => $context['claimant_relationship'] ?? null,
                    'release_remarks' => $context['release_remarks'] ?? null,
                ];
            }

            if ($target === CardStatus::Revoked) {
                $values += ['revoked_at' => now(), 'revocation_reason' => $reason];
            }

            if ($target === CardStatus::OnHold) {
                $values += [
                    'hold_previous_status' => $current->value,
                    'hold_reason' => $reason,
                    'held_at' => now(),
                    'held_by' => $actor->id,
                ];
            }

            if ($current === CardStatus::OnHold && $target !== CardStatus::OnHold) {
                $values += ['hold_resolved_at' => now(), 'hold_resolved_by' => $actor->id];
            }

            $locked->forceFill($values)->save();

            if ($target === CardStatus::Released) {
                IdRelease::create([
                    'id_card_id' => $locked->id,
                    'released_by' => $actor->id,
                    'released_at' => $locked->released_at,
                    'acknowledged_at' => $locked->release_acknowledged_at,
                    'claimant_name' => $context['claimant_name'] ?? null,
                    'claimant_relationship' => $context['claimant_relationship'] ?? null,
                    'remarks' => $context['release_remarks'] ?? null,
                ]);
            }

            $metadata = [];
            if ($current === CardStatus::OnHold) {
                $metadata['resolved_hold_reason'] = $locked->hold_reason;
            }
            if ($target === CardStatus::Released) {
                $metadata['release_acknowledged'] = true;
                $metadata['claimant_relationship'] = $context['claimant_relationship'] ?? null;
            }

            IdStatusHistory::create([
                'id_card_id' => $locked->id,
                'previous_status' => $current,
                'new_status' => $target,
                'user_id' => $actor->id,
                'reason' => $reason,
                'metadata' => $metadata ?: null,
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load('release');
        }, 3);
    }
}
