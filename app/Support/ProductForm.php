<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Product;
use App\Payments\KMoney\KMoneyPercentages;
use App\Payments\KMoney\KMoneyShare;
use App\Support\Images\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Il modulo prodotto, uguale per l'area azienda e per l'amministrazione.
 *
 * Controlla i campi, salva immagine e varianti e ricalcola la quota
 * KMoney. Chi lo usa decide solo di quale azienda e' il prodotto.
 */
class ProductForm
{
    public function __construct(
        private ImageStore $images,
        private KMoneyPercentages $percentages,
    ) {}

    /** Le quote KMoney che KMoney ammette per il conto dell'azienda. */
    public function kmoneySteps(Company $company): array
    {
        return KMoneyShare::steps($company->paymentSettings);
    }

    public function inDebt(Company $company): bool
    {
        return (bool) $company->paymentSettings?->kmoney_in_debt;
    }

    /** Crea o aggiorna il prodotto dell'azienda con i dati del modulo. */
    public function save(Request $request, Product $product, Company $company): Product
    {
        $data = $this->validated($request, $company);
        $variants = $data['variants'] ?? [];
        unset($data['variants']);

        // Con il conto KMoney in debito la quota e' 100 per tutti: la scelta sul prodotto non si tocca.
        if ($this->inDebt($company)) {
            unset($data['kmoney_discount_percent']);
        }

        $replaced = null;

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $this->images->store($request->file('featured_image'), 'products', 'product', 'featured_image');
            $replaced = $product->featured_image;
        }

        if (! $product->exists) {
            $data['company_id'] = $company->id;
            $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));
        }

        $product->fill($data)->save();
        $this->images->delete($replaced);
        $this->syncVariants($product, $variants);
        $this->percentages->refresh($company, [$product->id]);

        return $product;
    }

    private function validated(Request $request, Company $company): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'brand_id' => ['nullable', 'exists:product_brands,id'],
            'sku' => ['nullable', 'string', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            // Quota KMoney scelta sul prodotto; vuoto vuol dire automatica, da categoria o contratto.
            'kmoney_discount_percent' => ['nullable', Rule::in($this->kmoneySteps($company))],
            // Di norma il prodotto e' sempre disponibile; la giacenza conta solo se l'azienda la gestisce.
            'manage_stock' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0', Rule::requiredIf(fn () => $request->boolean('manage_stock') && $request->input('product_type') === 'simple')],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'fixed_shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'product_type' => ['required', 'in:simple,variable'],
            'status' => ['required', 'in:active,inactive'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288'],
            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.type' => ['nullable', 'string', 'max:60'],
            'variants.*.value' => ['nullable', 'string', 'max:120'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
        ], [
            'stock.required' => 'Indica la quantità disponibile, oppure togli la gestione della disponibilità.',
        ]);

        if ($data['product_type'] === 'variable') {
            $rows = collect($data['variants'] ?? [])->filter(fn ($row) => filled($row['type'] ?? null) || filled($row['value'] ?? null));
            if ($rows->isEmpty() || $rows->contains(fn ($row) => blank($row['type'] ?? null) || blank($row['value'] ?? null))) {
                throw ValidationException::withMessages([
                    'variants' => 'Inserisci almeno una variante e compila tipo e valore per ogni riga utilizzata.',
                ]);
            }
        }

        // Disponibilita' non gestita: giacenza vuota sul prodotto e sulle varianti, cioe' sempre disponibile.
        if (! $request->boolean('manage_stock')) {
            $data['stock'] = null;
            $data['variants'] = array_map(fn ($row) => ['stock' => null] + $row, $data['variants'] ?? []);
        }
        unset($data['manage_stock']);

        // L'editor manda HTML: si salva solo la formattazione ammessa.
        if (array_key_exists('description', $data)) {
            $data['description'] = RichText::clean($data['description']);
        }

        return $data;
    }

    /**
     * Allinea le varianti al modulo.
     *
     * Aggiorna le righe esistenti, crea quelle nuove compilate, elimina
     * quelle svuotate o tolte. Un prodotto semplice non ne tiene nessuna.
     * Le righe si cercano solo fra le varianti di questo prodotto.
     */
    private function syncVariants(Product $product, array $rows): void
    {
        if ($product->product_type !== 'variable') {
            $product->variants()->delete();

            return;
        }

        $kept = [];

        foreach ($rows as $row) {
            if (blank($row['type'] ?? null) && blank($row['value'] ?? null)) {
                continue;
            }

            $attributes = [
                'variant_type' => $row['type'] ?? null,
                'variant_value' => $row['value'] ?? null,
                'variant_price' => $row['price'] ?? null,
                'variant_stock' => $row['stock'] ?? null,
                'variant_sku' => $row['sku'] ?? null,
            ];

            $variant = filled($row['id'] ?? null)
                ? $product->variants()->whereKey($row['id'])->first()
                : null;

            if ($variant) {
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create($attributes);
            }

            $kept[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $kept)->delete();
    }
}
