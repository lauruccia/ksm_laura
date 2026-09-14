<?php

namespace App\Support;

class Money
{
    /** Formattazione unica per tutto il sito. */
    public static function format(float|string|null $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('ksm.currency');
        $symbol = match ($currency) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => $currency.' ',
        };

        return $symbol.' '.number_format((float) $amount, 2, ',', '.');
    }
}
