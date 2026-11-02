<?php

namespace App\Services;

final class QrPayloadResolver
{
    /** @return array{type: string, value: string}|null */
    public function interpret(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '' || strlen($payload) > 512 || preg_match('/[\x00-\x1F\x7F]/', $payload)) {
            return null;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $payload)) {
            return ['type' => 'card_token', 'value' => strtolower($payload)];
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,63}$/', $payload)) {
            return ['type' => 'student_number', 'value' => $payload];
        }

        return null;
    }
}
