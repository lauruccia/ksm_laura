<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Carrello in sessione.
 *
 * Vincolo del marketplace: un ordine appartiene a una sola azienda,
 * quindi il carrello in corso accetta prodotti di un venditore per volta.
 * I prodotti degli altri venditori non si perdono: il loro carrello resta
 * da parte e si riapre quando serve.
 */
class Cart
{
    private const KEY = 'cart';

    private const PARKED = 'cart_parked';

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

    public function company(): ?Company
    {
        return $this->companyId() ? Company::find($this->companyId()) : null;
    }

    /** I carrelli degli altri venditori, in attesa: [company_id => items]. */
    public function parked(): Collection
    {
        return collect(session(self::PARKED, []))->filter(fn ($items) => filled($items));
    }

    /** I pezzi di tutti i carrelli, per l'icona in intestazione. */
    public function totalCount(): int
    {
        return $this->count() + (int) $this->parked()->sum(fn ($items) => collect($items)->sum('quantity'));
    }

    /** Tutti i carrelli pronti da mostrare, quello in corso per primo: azienda, pezzi, totale. */
    public function summary(): Collection
    {
        $current = $this->companyId();
        $carts = ($current ? [$current => $this->items()->all()] : []) + $this->parked()->all();

        if ($carts === []) {
            return collect();
        }

        $companies = Company::whereKey(array_keys($carts))->get()->keyBy('id');

        return collect($carts)
            ->map(fn ($items, $companyId) => [
                'company' => $companies->get($companyId),
                'active' => $companyId === $current,
                'count' => (int) collect($items)->sum('quantity'),
                'subtotal' => (float) collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']),
            ])
            ->filter(fn ($cart) => $cart['company'] !== null)
            ->values();
    }

    /**
     * Apre il carrello di un venditore mettendo da parte quello in corso.
     * Niente viene buttato: i due carrelli restano finche' dura la sessione.
     */
    public function switchTo(int $companyId): void
    {
        $current = $this->companyId();

        if ($current === $companyId) {
            return;
        }

        $parked = $this->parked()->all();

        if ($current !== null) {
            $parked[$current] = $this->items()->all();
        }

        $next = $parked[$companyId] ?? [];
        unset($parked[$companyId]);

        session([self::PARKED => $parked]);
        $this->persist($next);
    }

    /** Butta via un carrello: quello in corso o uno in attesa. */
    public function discard(int $companyId): void
    {
        if ($this->companyId() === $companyId) {
            $this->clear();

            return;
        }

        $parked = $this->parked()->all();
        unset($parked[$companyId]);

        session([self::PARKED => $parked]);
    }

    /** Il carrello di un venditore, che sia quello in corso o uno in attesa. */
    private function cartFor(int $companyId): array
    {
        return $this->companyId() === $companyId
            ? $this->items()->all()
            : (array) ($this->parked()->get($companyId) ?? []);
    }

    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): void
    {
        $key = $this->key($product, $variant?->id);
        $existing = $this->cartFor($product->company_id)[$key]['quantity'] ?? 0;

        // Prima si controlla, poi si cambia carrello: una quantita' rifiutata
        // non deve spostare l'acquirente su un altro venditore.
        $this->validateSelection($product, $variant, max(1, $existing + $quantity));

        $this->switchTo($product->company_id);
        $items = $this->items()->all();

        $items[$key] = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'company_id' => $product->company_id,
            'name' => $product->name.($variant ? ' — '.$variant->label() : ''),
            'slug' => $product->slug,
            'image' => $product->featured_image,
            'price' => $variant ? $variant->priceFor($product) : $product->final_price,
            'quantity' => max(1, $existing + $quantity),
        ];

        $this->persist($items);
    }

    public function updateQuantity(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        $items = $this->items()->all();
        $key = $this->key($product, $variant?->id);

        if (! isset($items[$key])) {
            return;
        }

        if ($quantity < 1) {
            unset($items[$key]);
        } else {
            $this->validateSelection($product, $variant, $quantity);
            $items[$key]['quantity'] = $quantity;
        }

        $this->persist($items);
    }

    public function remove(Product $product, ?int $variantId = null): void
    {
        $items = $this->items()->all();
        unset($items[$this->key($product, $variantId)]);

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

    private function key(Product $product, ?int $variantId): string
    {
        return $product->id.($variantId ? ':'.$variantId : '');
    }

    private function validateSelection(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if (($product->product_type === 'variable') !== ($variant !== null) || ($variant && $variant->product_id !== $product->id)) {
            throw ValidationException::withMessages(['variant_id' => 'Scegli una variante valida del prodotto.']);
        }
        $stock = $variant ? $variant->variant_stock : $product->stock;
        if ($product->status !== 'active' || (! blank($stock) && $quantity > (int) $stock)) {
            throw ValidationException::withMessages(['quantita' => 'Quantità non disponibile per '.$product->name.'.']);
        }
    }

    /** Recheck availability and prices before creating an order from the session. */
    public function refresh(): void
    {
        $items = $this->items()->all();
        $products = Product::with('variants')->whereKey(array_column($items, 'product_id'))->get()->keyBy('id');
        foreach ($items as &$item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['cart' => 'Un prodotto nel carrello non è più disponibile.']);
            }
            $variant = filled($item['variant_id'] ?? null) ? $product->variants->firstWhere('id', $item['variant_id']) : null;
            if (filled($item['variant_id'] ?? null) && ! $variant) {
                throw ValidationException::withMessages(['cart' => 'Una variante nel carrello non è più disponibile. Rimuovila e scegli nuovamente.']);
            }
            $this->validateSelection($product, $variant, $item['quantity']);
            $item['price'] = $variant ? $variant->priceFor($product) : $product->final_price;
            $item['name'] = $product->name.($variant ? ' — '.$variant->label() : '');
        }
        unset($item);
        $this->persist($items);
    }
}
