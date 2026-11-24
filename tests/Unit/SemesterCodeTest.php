<?php

namespace Tests\Unit;

use App\Enums\SemesterCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SemesterCodeTest extends TestCase
{
    #[DataProvider('semesterValues')]
    public function test_numeric_and_legacy_values_map_without_reinterpretation(mixed $input, SemesterCode $expected): void
    {
        $semester = SemesterCode::fromInput($input);

        $this->assertSame($expected, $semester);
        $this->assertSame($expected->label(), $semester->label());
        $this->assertSame($expected->databaseCode(), $semester->databaseCode());
    }

    public static function semesterValues(): array
    {
        return [
            'numeric zero' => [0, SemesterCode::Summer],
            'string zero' => ['0', SemesterCode::Summer],
            'legacy summer' => ['summer', SemesterCode::Summer],
            'numeric one' => [1, SemesterCode::First],
            'legacy first' => ['first_semester', SemesterCode::First],
            'numeric two' => [2, SemesterCode::Second],
            'legacy second' => ['second_semester', SemesterCode::Second],
        ];
    }

    public function test_unknown_semester_is_rejected_instead_of_guessed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SemesterCode::fromInput('special_term');
    }
}