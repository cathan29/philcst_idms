<?php

namespace App\Enums;

use InvalidArgumentException;

/** Numeric panel semester codes with explicit aliases for existing records. */
enum SemesterCode: int
{
    case Summer = 0;
    case First = 1;
    case Second = 2;

    public static function fromInput(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '0', 'summer', 'summer term' => self::Summer,
            '1', 'first', 'first_semester', 'first semester', '1st', '1st semester' => self::First,
            '2', 'second', 'second_semester', 'second semester', '2nd', '2nd semester' => self::Second,
            default => throw new InvalidArgumentException('Unsupported semester value.'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Summer => 'Summer',
            self::First => 'First Semester',
            self::Second => 'Second Semester',
        };
    }

    public function databaseCode(): string
    {
        return match ($this) {
            self::Summer => 'summer',
            self::First => 'first_semester',
            self::Second => 'second_semester',
        };
    }
}