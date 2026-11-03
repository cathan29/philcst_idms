<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\IdStatusHistory;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CardStatusTransitionService
{
    public function transition(IdCard $card, CardStatus $target, User $actor, ?string $reason = null): IdCard
    {
        return DB::transaction(function () use ($card, $target, $actor, $reason) {
            $locked = IdCard::lockForUpdate()->findOrFail($card->id);
            $current = $locked->status;
            if ($current === $target) {
                throw new DomainException('This production status is already confirmed.');
            }if (! $current->canTransitionTo($target)) {
                throw new DomainException("Cards cannot transition from {$current->value} to {$target->value}.");
            }if (in_array($target, [CardStatus::OnHold, CardStatus::Cancelled, CardStatus::Revoked], true) && blank($reason)) {
                throw new DomainException('A documented reason is required.');
            }if ($current === CardStatus::OnHold && ! in_array($target, [CardStatus::Cancelled, CardStatus::Revoked], true) && $locked->hold_previous_status !== $target->value) {
                throw new DomainException('An on-hold request may only resume its previous workflow state.');
            }$values = ['status' => $target];
            if ($target === CardStatus::ForPrinting && blank($locked->printing_started_at)) {
                $values += ['printing_started_at' => now(), 'assigned_staff_id' => $actor->id];
            }if ($target === CardStatus::Printed) {
                $values += ['printed_at' => now(), 'printed_by' => $actor->id];
            }if ($target === CardStatus::ReadyForRelease) {
                $values += ['ready_at' => now()];
            }if ($target === CardStatus::Released) {
                $values += ['released_at' => now(), 'released_by' => $actor->id];
            }if ($target === CardStatus::Revoked) {
                $values += ['revoked_at' => now(), 'revocation_reason' => $reason];
            }if ($target === CardStatus::OnHold) {
                $values += ['hold_previous_status' => $current->value, 'hold_reason' => $reason, 'held_at' => now(), 'held_by' => $actor->id];
            }if ($current === CardStatus::OnHold && $target !== CardStatus::OnHold) {
                $values += ['hold_resolved_at' => now(), 'hold_resolved_by' => $actor->id];
            }$locked->forceFill($values)->save();
            IdStatusHistory::create(['id_card_id' => $locked->id, 'previous_status' => $current, 'new_status' => $target, 'user_id' => $actor->id, 'reason' => $reason, 'metadata' => $current === CardStatus::OnHold ? ['resolved_hold_reason' => $locked->hold_reason] : null, 'occurred_at' => now()]);

            return $locked->refresh();
        }, 3);
    }
}
