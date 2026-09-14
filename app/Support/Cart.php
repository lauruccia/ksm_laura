<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Carrello in sessione.
 *
 * Vincolo del marketplace: un ordine appartiene a una sola azienda,
 * quindi il carrello accetta prodotti di un venditore per volta.
 */
class Cart
{
    private const KEY = 'cart';

    public function items(): Collection
    {
        return collect(session(self::KEY, []));
    }

    public function count(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }

    public function companyId(): ?int
    {
        return $this->items()->pluck('company_id')->unique()->first();
    }

    public function belongsToOtherCompany(Product $product): bool
    {
        $current = $this->companyId();

        return $current !== null && $current !== $product->company_id;
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->items()->all();
        $key = (string) $product->id;
        $existing = $items[$key]['quantity'] ?? 0;

        $items[$key] = [
            'product_id' => $product->id,
            'company_id' => $product->company_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'image' => $product->featured_image,
            'price' => $product->final_price,
            'quantity' => max(1, $existing + $quantity),
        ];

        $this->persist($items);
    }

    public function updateQuantity(Product $product, int $quantity): void
    {
        $items = $this->items()->all();
        $key = (string) $product->id;

        if (! isset($items[$key])) {
            return;
        }

        if ($quantity < 1) {
            unset($items[$key]);
        } else {
            $items[$key]['quantity'] = $quantity;
        }

        $this->persist($items);
    }

    public function remove(Product $product): void
    {
        $items = $this->items()->all();
        unset($items[(string) $product->id]);

        $this->persist($items);
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum(fn ($item) => $item['price'] * $item['quantity']);
    }

    private function persist(array $items): void
    {
        session([self::KEY => $items]);
    }
}
