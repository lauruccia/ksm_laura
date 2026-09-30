<?php

namespace App\Integrations\ManagementApi\Support;

use InvalidArgumentException;

final class Decimal
{
    public static function cents(int|float|string|null $value): int
    {
        $value = trim((string) ($value ?? '0'));

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw new InvalidArgumentException("Invalid monetary value: {$value}");
        }

        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }
}
