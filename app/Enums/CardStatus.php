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
    case Cancelled = 'cancelled';
    case Revoked = 'revoked';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::ForPrinting, self::OnHold, self::Cancelled],self::ForPrinting => [self::Printed, self::OnHold, self::Cancelled],self::Printed => [self::ReadyForRelease, self::OnHold],self::ReadyForRelease => [self::Released, self::OnHold],self::OnHold => [self::Pending, self::ForPrinting, self::Printed, self::ReadyForRelease, self::Cancelled],self::Released => [self::Revoked],self::Cancelled,self::Revoked => []
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
