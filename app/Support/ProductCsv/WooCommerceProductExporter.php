<?php

namespace App\Support\ProductCsv;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Esporta i prodotti di un'azienda in un CSV nel formato di WooCommerce.
 *
 * E' il file inverso di WooCommerceProductImporter: lo stesso formato che
 * esporta kmoney, quindi si importa in ksm, in kmoney e in WooCommerce.
 * La quota pagabile in KMoney esce nella colonna `Meta: _kmoney_ky_percentage`
 * solo se e' stata scelta sul prodotto; vuota resta automatica (categoria o contratto).
 *
 * Il prodotto si riconosce dallo SKU. La colonna ID resta vuota (in WooCommerce un
 * ID gia' presente aggiornerebbe un altro prodotto), tranne per i prodotti variabili
 * senza SKU: lì serve per legare le variazioni (`Parent` = `id:123`).
 */
final class WooCommerceProductExporter
{
    private const MAX_ATTRIBUTES = 3;

    public const HEADER = [
        'ID', 'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Visibility in catalog',
        'Short description', 'Description', 'In stock?', 'Stock', 'Regular price', 'Sale price',
        'Weight (kg)', 'Categories', 'Images', 'Parent',
        'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global',
        'Attribute 2 name', 'Attribute 2 value(s)', 'Attribute 2 visible', 'Attribute 2 global',
        'Attribute 3 name', 'Attribute 3 value(s)', 'Attribute 3 visible', 'Attribute 3 global',
        'Meta: _kmoney_ky_percentage',
    ];

    /**
     * Scrive il CSV (con BOM, per Excel) su $handle. Con $withCompany aggiunge in fondo
     * la colonna "Azienda" (WooCommerce e l'import la ignorano).
     *
     * @param  Builder<Product>  $products
     * @return array{rows: int, products: int, noSku: int}
     */
    public function write($handle, Builder $products, bool $withCompany = false): array
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $withCompany ? [...self::HEADER, 'Azienda'] : self::HEADER, ',', '"', '');

        $out = ['rows' => 0, 'products' => 0, 'noSku' => 0];

        $with = $withCompany ? ['category.parent', 'variants', 'company'] : ['category.parent', 'variants'];

        $products->with($with)->chunkById(200, function ($chunk) use ($handle, $withCompany, &$out) {
            foreach ($chunk as $product) {
                $out['products']++;
                if (blank($product->sku)) {
                    $out['noSku']++;
                }
                foreach ($this->rows($product) as $row) {
                    if ($withCompany) {
                        $row['Azienda'] = (string) $product->company?->name;
                    }
                    fputcsv($handle, $row, ',', '"', '');
                    $out['rows']++;
                }
            }
        });

        return $out;
    }

    /** @return list<array<string, string>> le righe (prodotto e variazioni) con le colonne di HEADER */
    public function rows(Product $p): array
    {
        $variable = $p->product_type === 'variable';
        $variants = $variable ? $p->variants->values() : collect();
        $ref = $this->reference($p);
        [$attrNames, $attrValues] = $variable ? $this->parentAttributes($variants) : [[], []];

        $row = $this->blank();
        $row['ID'] = $variable && blank($p->sku) ? (string) $p->id : '';
        $row['Type'] = $variable ? 'variable' : 'simple';
        $row['SKU'] = (string) $p->sku;
        $row['Name'] = (string) $p->name;
        $row['Published'] = $p->status === 'active' ? '1' : '0';
        $row['Is featured?'] = '0';
        $row['Visibility in catalog'] = 'visible';
        $row['Short description'] = (string) $p->short_description;
        $row['Description'] = (string) $p->description;
        $row['In stock?'] = $p->isInStock() ? '1' : '0';
        $row['Weight (kg)'] = $this->number($p->weight_kg);
        $row['Categories'] = $this->categoryPath($p->category);
        $row['Images'] = implode(', ', $this->images($p));
        $row['Meta: _kmoney_ky_percentage'] = $p->kmoney_discount_percent === null ? '' : (string) (int) $p->kmoney_discount_percent;

        if (! $variable) {
            $row['Stock'] = $p->stock === null ? '' : (string) (int) $p->stock;
            $row['Regular price'] = $this->number($p->price);
            $row['Sale price'] = $this->sale($p);
        }

        foreach ($attrNames as $i => $name) {
            $n = $i + 1;
            $row["Attribute {$n} name"] = $name;
            $row["Attribute {$n} value(s)"] = implode(', ', $attrValues[$i]);
            $row["Attribute {$n} visible"] = '1';
            $row["Attribute {$n} global"] = '0';
        }

        $rows = [$row];

        foreach ($variants as $v) {
            $r = $this->blank();
            $r['Type'] = 'variation';
            $r['SKU'] = (string) $v->variant_sku;
            $r['Name'] = $p->name . ' - ' . $v->variant_value;
            $r['Published'] = '1';
            $r['In stock?'] = $v->isInStock() ? '1' : '0';
            $r['Stock'] = blank($v->variant_stock) ? '' : (string) (int) $v->variant_stock;
            $r['Regular price'] = $this->number($v->variant_price ?? $p->price);
            $r['Parent'] = $ref;

            [$names, $values] = $this->split($v);
            foreach ($names as $i => $name) {
                $n = $i + 1;
                $r["Attribute {$n} name"] = $name;
                $r["Attribute {$n} value(s)"] = $values[$i];
                $r["Attribute {$n} global"] = '0';
            }

            $rows[] = $r;
        }

        return $rows;
    }

    /** Come le variazioni indicano il prodotto: SKU, oppure `id:` se non ce l'ha. */
    private function reference(Product $p): string
    {
        return filled($p->sku) ? (string) $p->sku : 'id:' . $p->id;
    }

    /** @return array<string, string> */
    private function blank(): array
    {
        return array_fill_keys(self::HEADER, '');
    }

    /**
     * "Taglia / Colore" + "M / Rosso" -> due attributi. Se i pezzi non tornano
     * o sono troppi, resta un attributo solo con il testo intero.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function split(ProductVariant $v): array
    {
        $type = trim((string) $v->variant_type);
        $value = trim((string) $v->variant_value);
        $names = array_map('trim', explode(' / ', $type));
        $values = array_map('trim', explode(' / ', $value));

        if (count($names) !== count($values) || count($names) > self::MAX_ATTRIBUTES) {
            return [[$type !== '' ? $type : 'Opzione'], [$value]];
        }

        return [array_map(fn ($n) => $n !== '' ? $n : 'Opzione', $names), $values];
    }

    /**
     * Gli attributi del prodotto variabile: il nome preso dalla prima variante
     * che ha quella posizione, i valori distinti di tutte le varianti.
     *
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function parentAttributes($variants): array
    {
        $names = [];
        $values = [];

        foreach ($variants as $v) {
            [$n, $val] = $this->split($v);
            foreach ($n as $i => $name) {
                $names[$i] ??= $name;
                if (! in_array($val[$i], $values[$i] ?? [], true)) {
                    $values[$i][] = $val[$i];
                }
            }
        }

        ksort($names);
        ksort($values);

        return [array_values($names), array_values($values)];
    }

    /** "Madre > Figlia" (la virgola separa le categorie in WooCommerce: via dai nomi). */
    private function categoryPath(?ProductCategory $category): string
    {
        $names = [];
        for ($c = $category, $guard = 0; $c !== null && $guard < 5; $c = $c->parent, $guard++) {
            array_unshift($names, trim(str_replace(',', ' ', $c->name)));
        }

        return implode(' > ', $names);
    }

    /** @return list<string> prima la principale, poi la galleria; URL assoluti */
    private function images(Product $p): array
    {
        $paths = array_merge([$p->featured_image], is_array($p->gallery_images) ? $p->gallery_images : []);

        $urls = [];
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $urls[] = preg_match('#^https?://#i', $path) ? $path : asset('storage/' . ltrim($path, '/'));
            }
        }

        return array_values(array_unique($urls));
    }

    private function sale(Product $p): string
    {
        $discount = (float) $p->discount_price;

        return $discount > 0 && $discount < (float) $p->price ? $this->number($discount) : '';
    }

    private function number(mixed $value): string
    {
        return $value === null || $value === '' ? '' : number_format((float) $value, 2, '.', '');
    }
}
