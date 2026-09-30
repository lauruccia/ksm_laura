<?php

namespace App\Integrations\ManagementApi\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class Cursor
{
    public static function encode(string $updatedAt, int $id): string
    {
        return rtrim(strtr(base64_encode(json_encode(['updated_at' => $updatedAt, 'id' => $id], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{updated_at: CarbonImmutable, id: int} */
    public static function decode(string $cursor): array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $payload = $decoded === false ? null : json_decode($decoded, true);

        if (! is_array($payload) || ! is_string($payload['updated_at'] ?? null) || ! is_int($payload['id'] ?? null) || $payload['id'] < 1) {
            throw new InvalidArgumentException('Invalid cursor.');
        }

        try {
            return ['updated_at' => CarbonImmutable::parse($payload['updated_at'])->utc(), 'id' => $payload['id']];
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid cursor.');
        }
    }
}
