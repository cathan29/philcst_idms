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
            $locked = IdCard::query()->lockForUpdate()->findOrFail($card->getKey());
            $current = $locked->status;

            if ($current === $target) {
                return $locked;
            }
            if (! $current->canTransitionTo($target)) {
                throw new DomainException("Cards cannot transition from {$current->value} to {$target->value}.");
            }
            if (in_array($target, [CardStatus::OnHold, CardStatus::Revoked], true) && blank($reason)) {
                throw new DomainException('A reason is required for hold or revocation.');
            }

            $timestamps = match ($target) {
                CardStatus::Printed => ['printed_at' => now(), 'printed_by' => $actor->id],
                CardStatus::ReadyForRelease => ['ready_at' => now()],
                CardStatus::Released => ['released_at' => now(), 'released_by' => $actor->id],
                CardStatus::Revoked => ['revoked_at' => now(), 'revocation_reason' => $reason],
                default => [],
            };

            $locked->forceFill(['status' => $target, ...$timestamps])->save();
            IdStatusHistory::create([
                'id_card_id' => $locked->id,
                'previous_status' => $current,
                'new_status' => $target,
                'user_id' => $actor->id,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }
}
