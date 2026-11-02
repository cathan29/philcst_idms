<?php

namespace Tests\Unit;

use App\Services\QrPayloadResolver;
use PHPUnit\Framework\TestCase;

class QrPayloadResolverTest extends TestCase
{
    public function test_it_accepts_opaque_tokens_and_student_numbers(): void
    {
        $resolver = new QrPayloadResolver;
        $this->assertSame(['type' => 'card_token', 'value' => '550e8400-e29b-41d4-a716-446655440000'], $resolver->interpret('550E8400-E29B-41D4-A716-446655440000'));
        $this->assertSame(['type' => 'student_number', 'value' => '0012-AB'], $resolver->interpret('0012-AB'));
    }

    public function test_it_rejects_urls_control_characters_and_oversized_payloads(): void
    {
        $resolver = new QrPayloadResolver;
        $this->assertNull($resolver->interpret('https://untrusted.example/path'));
        $this->assertNull($resolver->interpret("ABC\nDEF"));
        $this->assertNull($resolver->interpret(str_repeat('A', 513)));
    }
}
