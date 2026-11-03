<?php

namespace App\Enums;

enum IdIssuanceType: string
{
    case Initial = 'initial';
    case Replacement = 'replacement';
    case Lost = 'lost';
    case Damaged = 'damaged';
    case Reissue = 'reissue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'First ID',
            self::Replacement => 'Replacement ID',
            self::Lost => 'Lost ID',
            self::Damaged => 'Damaged ID',
            self::Reissue => 'Approved Reissuance',
            self::Other => 'Other Authorized Reason',
        };
    }

    public function requiresPreviousCard(): bool
    {
        return in_array($this, [self::Replacement, self::Lost, self::Damaged, self::Reissue], true);
    }
}
