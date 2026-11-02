<?php

namespace App\Enums;

enum CardStatus: string
{
    case Pending = 'pending';
    case ForPrinting = 'for_printing';
    case Printed = 'printed';
    case ReadyForRelease = 'ready_for_release';
    case Released = 'released';
    case OnHold = 'on_hold';
    case Revoked = 'revoked';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::ForPrinting, self::OnHold, self::Revoked],
            self::ForPrinting => [self::Printed, self::OnHold, self::Revoked],
            self::Printed => [self::ReadyForRelease, self::OnHold, self::Revoked],
            self::ReadyForRelease => [self::Released, self::OnHold, self::Revoked],
            self::OnHold => [self::Pending, self::ForPrinting, self::Printed, self::ReadyForRelease, self::Revoked],
            self::Released => [self::Revoked],
            self::Revoked => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
