<?php

namespace App\Support\Orders;

use App\Models\Company;
use App\Models\Domain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Payments\KMoney\KMoneySplitter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Crea e modifica gli ordini dall'amministrazione.
 *
 * Righe: ognuna e' un prodotto dell'azienda dell'ordine (o una sua
 * variante), con quantita' e prezzo; il prezzo vuoto prende quello di
 * oggi del prodotto. Totali ricalcolati: somma delle righe piu' spedizione.
 *
 * Disponibilita': se l'ordine l'aveva scalata, le righe vecchie la
 * restituiscono e le nuove la riprendono, secondo lo stato (OrderStock).
 *
 * Pagamenti: non si toccano. Cambiare gli importi di un ordine gia' pagato
 * online non cambia cio' che il cliente ha pagato.
 */
class OrderEditor
{
    public const BLANK_ROWS = 4;

    public function __construct(private OrderStock $stock)
    {
    }

    public static function rules(bool $creating): array
    {
        return [
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:120'],
            'billing_zip' => ['nullable', 'string', 'max:20'],
            'billing_country' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'shipping' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product' => ['nullable', 'string', 'max:40'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.remove' => ['nullable', 'boolean'],
        ] + ($creating ? [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'site' => ['required', 'string', 'max:40'],
            'status' => ['required', Rule::in(['pending', 'paid', 'shipped', 'completed'])],
        ] : []);
    }

    /**
     * I siti da cui puo' "arrivare" un ordine inserito a mano per l'azienda.
     *
     * @return array<string, string>  valore del modulo => etichetta
     */
    public static function siteOptions(Company $company): array
    {
        return ['platform' => config('ksm.brand_name').' (sito principale)']
            + Domain::query()->orderBy('domain')->pluck('domain', 'id')->mapWithKeys(fn ($host, $id) => ["domain:$id" => $host])->all()
            + ($company->custom_domain ? ['company' => $company->custom_domain.' (sito dell\'azienda)'] : []);
    }

    /**
     * Prodotti e varianti dell'azienda per le righe: "p:12" o "v:12:5".
     *
     * @return array<string, string>
     */
    public static function productOptions(Company $company): array
    {
        $options = [];

        foreach ($company->products()->with('variants')->orderBy('name')->get() as $product) {
            if ($product->variants->isEmpty()) {
                $options["p:$product->id"] = $product->name.' · '.number_format($product->final_price, 2, ',', '.').' €';
            }

            foreach ($product->variants as $variant) {
                $options["v:$product->id:$variant->id"] = $product->name.' — '.$variant->label().' · '.number_format($variant->priceFor($product), 2, ',', '.').' €';
            }
        }

        return $options;
    }

    public function create(Company $company, array $data): Order
    {
        [$site, $domainId] = $this->site($company, $data['site']);

        return DB::transaction(function () use ($company, $data, $site, $domainId) {
            $order = Order::create($this->details($data) + [
                'company_id' => $company->id,
                // L'account del cliente, se ne ha uno con questa email: ritrova l'ordine nella sua area.
                'user_id' => User::query()->where('email', $data['billing_email'])->value('id'),
                'site' => $site,
                'domain_id' => $domainId,
                'status' => $data['status'],
                'currency' => config('ksm.currency'),
                'shipped_at' => $data['status'] === 'shipped' ? now() : null,
            ]);

            $this->saveItems($order, $company, $data['items'] ?? []);
            $this->stock->sync($order);

            return $order;
        });
    }

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            // Le righe cambiano: prima si restituisce cio' che l'ordine aveva preso.
            $this->stock->restore($order);

            $order->update($this->details($data));
            $this->saveItems($order, $order->company, $data['items'] ?? []);

            $this->stock->sync($order->refresh());

            return $order;
        });
    }

    private function details(array $data): array
    {
        return collect($data)->only([
            'billing_name', 'billing_email', 'billing_phone', 'billing_address', 'billing_city',
            'billing_state', 'billing_zip', 'billing_country', 'notes',
        ])->all() + ['shipping' => (float) ($data['shipping'] ?? 0)];
    }

    /** @return array{0: string, 1: ?int} */
    private function site(Company $company, string $value): array
    {
        return match (true) {
            $value === 'platform' => ['platform', null],
            $value === 'company' && filled($company->custom_domain) => ['company', null],
            str_starts_with($value, 'domain:') && Domain::whereKey((int) substr($value, 7))->exists() => ['domain', (int) substr($value, 7)],
            default => throw ValidationException::withMessages(['site' => __('Scegli da quale sito arriva l\'ordine.')]),
        };
    }

    private function saveItems(Order $order, ?Company $company, array $rows): void
    {
        abort_unless($company, 404);

        $existing = $order->items()->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $index => $row) {
            $item = isset($row['id']) ? $existing->get((int) $row['id']) : null;

            if (! empty($row['remove'])) {
                continue;
            }

            if (! $item && blank($row['product'] ?? null)) {
                continue; // riga vuota del modulo
            }

            $quantity = (int) ($row['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages(["items.$index.quantity" => __('Indica una quantita\'.')]);
            }

            if (! $item) {
                $item = $this->newItem($company, (string) $row['product'], $index);
            }

            $price = filled($row['price'] ?? null) ? round((float) $row['price'], 2) : (float) $item->product_price;
            $subtotal = round($price * $quantity, 2);

            $item->fill([
                'order_id' => $order->id,
                'product_price' => $price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                // Stessa quota di prima sul nuovo importo; le righe nuove non ne hanno.
                'kmoney_percent' => (int) $item->kmoney_percent,
                'kmoney_amount' => intdiv(KMoneySplitter::cents($subtotal) * (int) $item->kmoney_percent, 100) / 100,
            ])->save();

            $kept[] = $item->id;
        }

        if (! $kept) {
            throw ValidationException::withMessages(['items' => __('L\'ordine deve avere almeno una riga.')]);
        }

        $order->items()->whereNotIn('id', $kept)->delete();

        $items = $order->items()->get();
        $subtotal = round((float) $items->sum('subtotal'), 2);

        $order->update([
            'subtotal' => $subtotal,
            'total' => round($subtotal + (float) $order->shipping, 2),
            'kmoney_total' => round((float) $items->sum('kmoney_amount'), 2),
        ]);
    }

    private function newItem(Company $company, string $value, int $index): OrderItem
    {
        $parts = explode(':', $value);
        $product = Product::query()->where('company_id', $company->id)->find((int) ($parts[1] ?? 0));
        $variant = $product && $parts[0] === 'v'
            ? ProductVariant::query()->where('product_id', $product->id)->find((int) ($parts[2] ?? 0))
            : null;

        if (! $product || ($parts[0] === 'v' && ! $variant)) {
            throw ValidationException::withMessages(["items.$index.product" => __('Scegli un prodotto dell\'azienda.')]);
        }

        return new OrderItem([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name.($variant ? ' — '.$variant->label() : ''),
            'product_price' => $variant ? $variant->priceFor($product) : $product->final_price,
            'kmoney_percent' => 0,
        ]);
    }
}
