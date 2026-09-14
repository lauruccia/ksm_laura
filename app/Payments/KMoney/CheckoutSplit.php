<?php

namespace App\Payments\KMoney;

/**
 * Un carrello diviso fra KMoney ed euro.
 *
 * Gli importi stanno in centesimi, interi: sommati e divisi non perdono
 * centesimi per strada. 1 KY vale 1 euro.
 */
final class CheckoutSplit
{
    /**
     * @param  array<int, array{percent: int, kmoney: int}>  $lines  prodotto => quota e centesimi in KMoney
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $shippingPercent,
        public readonly int $totalCents,
        public readonly int $kmoneyCents,
        public readonly bool $vendorInDebt,
    ) {
    }

    public function euroCents(): int
    {
        return $this->totalCents - $this->kmoneyCents;
    }

    public function hasKmoney(): bool
    {
        return $this->kmoneyCents > 0;
    }

    public function hasEuro(): bool
    {
        return $this->euroCents() > 0;
    }

    public function kmoney(): float
    {
        return $this->kmoneyCents / 100;
    }

    public function euro(): float
    {
        return $this->euroCents() / 100;
    }

    public function percentFor(int $productId): int
    {
        return $this->lines[$productId]['percent'] ?? 0;
    }

    public function kmoneyFor(int $productId): float
    {
        return ($this->lines[$productId]['kmoney'] ?? 0) / 100;
    }
}
