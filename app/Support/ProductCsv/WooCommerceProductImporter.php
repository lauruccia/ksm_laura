<?php

namespace App\Support\ProductCsv;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Payments\KMoney\KMoneyPercentages;
use App\Payments\KMoney\KMoneyShare;
use App\Support\Images\ImageStore;
use App\Support\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Importa i prodotti di un'azienda da un CSV nel formato di WooCommerce.
 *
 * E' lo stesso file che esporta kmoney (e che esporta WooCommerce): la quota
 * pagabile in KMoney arriva dalla colonna `Meta: _kmoney_ky_percentage` e
 * diventa la scelta sul prodotto (`kmoney_discount_percent`), nei limiti delle
 * quote ammesse al conto. Il prodotto si riconosce dallo SKU, dentro l'azienda:
 * se c'e' si aggiorna, altrimenti si crea.
 *
 * Due tempi, come in kmoney: plan() non scrive niente e dice cosa
 * succederebbe; apply() lo fa. Riconosce le intestazioni inglesi e italiane.
 */
final class WooCommerceProductImporter
{
    public const MAX_ROWS = 1000;
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    private const ALIASES = [
        'id' => 'id',
        'type' => 'type', 'tipo' => 'type',
        'sku' => 'sku',
        'name' => 'name', 'nome' => 'name',
        'published' => 'published', 'pubblicato' => 'published',
        'short description' => 'short', 'breve descrizione' => 'short', 'descrizione breve' => 'short',
        'description' => 'description', 'descrizione' => 'description',
        'in stock?' => 'instock', 'disponibilità?' => 'instock', 'disponibilita?' => 'instock',
        'stock' => 'stock', 'magazzino' => 'stock',
        'regular price' => 'price', 'prezzo di listino' => 'price', 'prezzo regolare' => 'price',
        'sale price' => 'sale', 'prezzo in offerta' => 'sale',
        'weight (kg)' => 'weight', 'peso (kg)' => 'weight',
        'categories' => 'categories', 'categorie' => 'categories',
        'images' => 'images', 'immagini' => 'images',
        'parent' => 'parent', 'superiore' => 'parent', 'genitore' => 'parent',
        'meta: _kmoney_ky_percentage' => 'pct',
    ];

    /**
     * @param  array{images?: bool} $opts
     * @return array{fatal: ?string, items: list<array>, counts: array<string,int>}
     */
    public function plan(string $path, Company $company, array $opts = []): array
    {
        $empty = ['items' => [], 'counts' => ['create' => 0, 'update' => 0, 'same' => 0, 'error' => 0]];

        $parsed = $this->readCsv($path);
        if ($parsed['error'] !== null) {
            return ['fatal' => $parsed['error']] + $empty;
        }

        $settings = $company->paymentSettings;
        $ctx = [
            'company' => $company,
            'steps'   => KMoneyShare::steps($settings),
            'inDebt'  => (bool) $settings?->kmoney_in_debt,
            'cats'    => $this->categoryMap(),
        ];

        $parents = [];
        $variations = [];
        foreach ($parsed['rows'] as $n => $row) {
            $row['_line'] = $n + 2;
            ($row['type'] ?? '') === 'variation' ? $variations[] = $row : $parents[] = $row;
        }

        $perParent = [];
        $orfane = [];
        foreach ($variations as $v) {
            $ref = trim((string) ($v['parent'] ?? ''));
            $key = null;
            foreach ($parents as $i => $p) {
                if ($ref !== '' && (($p['sku'] ?? '') === $ref || ('id:' . ($p['id'] ?? '')) === $ref)) {
                    $key = $i;
                    break;
                }
            }
            $key === null ? $orfane[] = $v : $perParent[$key][] = $v;
        }

        $items = [];
        foreach ($parents as $i => $row) {
            $items[] = $this->buildItem($row, $perParent[$i] ?? [], $ctx);
        }
        foreach ($orfane as $v) {
            $items[] = $this->errorItem($v, 'Variazione senza prodotto "variabile" nel file (colonna Parent: "' . ($v['parent'] ?? '') . '").');
        }

        $counts = ['create' => 0, 'update' => 0, 'same' => 0, 'error' => 0];
        foreach ($items as $it) {
            $counts[$it['action']]++;
        }

        return ['fatal' => null, 'items' => $items, 'counts' => $counts];
    }

    /** @return array{created: int, updated: int, skipped: int, errors: list<string>} */
    public function apply(array $plan, Company $company, array $opts = []): array
    {
        $out = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $touched = [];

        foreach ($plan['items'] as $it) {
            if ($it['action'] === 'error') {
                $out['errors'][] = "Riga {$it['line']} ({$it['name']}): " . implode(' ', $it['errors']);
                continue;
            }
            if ($it['action'] === 'same') {
                $out['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($it, $company, $opts, &$out, &$touched) {
                    $product = $it['action'] === 'create'
                        ? $this->create($it, $company, $opts)
                        : $this->update($it, $company, $opts);
                    $touched[] = $product->id;
                    $out[$it['action'] === 'create' ? 'created' : 'updated']++;
                });
            } catch (\Throwable $e) {
                report($e);
                $out['errors'][] = "Riga {$it['line']} ({$it['name']}): salvataggio non riuscito.";
            }
        }

        // La quota effettiva (prodotto, categoria, contratto) si ricalcola una volta sola.
        if ($touched !== []) {
            app(KMoneyPercentages::class)->refresh($company, $touched);
        }

        return $out;
    }

    // ── Lettura del file ─────────────────────────────────────────────────────

    /** @return array{error: ?string, rows: list<array<string,string>>} */
    private function readCsv(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return ['error' => 'Il file e\' vuoto o non si riesce a leggere.', 'rows' => []];
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($raw, "\n") ?: '';
        $delim = ',';
        foreach ([';', "\t"] as $cand) {
            if (substr_count($firstLine, $cand) > substr_count($firstLine, $delim)) {
                $delim = $cand;
            }
        }

        $h = fopen('php://memory', 'r+');
        fwrite($h, $raw);
        rewind($h);

        $headerRow = fgetcsv($h, 0, $delim, '"', '');
        if (! $headerRow) {
            fclose($h);
            return ['error' => 'Non trovo le intestazioni nella prima riga del file.', 'rows' => []];
        }

        $map = [];
        foreach ($headerRow as $idx => $name) {
            $key = $this->headerKey((string) $name);
            if ($key !== null) {
                $map[$idx] = $key;
            }
        }

        if (! in_array('name', $map, true) || ! in_array('type', $map, true)) {
            fclose($h);
            return ['error' => 'Non sembra un export di WooCommerce: mancano le colonne "Type"/"Tipo" e "Name"/"Nome".', 'rows' => []];
        }

        $rows = [];
        while (($cells = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($map as $idx => $key) {
                $row[$key] = trim((string) ($cells[$idx] ?? ''));
            }
            $rows[] = $row;

            if (count($rows) > self::MAX_ROWS) {
                fclose($h);
                return ['error' => 'Il file ha piu\' di ' . self::MAX_ROWS . ' righe: dividilo in file piu\' piccoli.', 'rows' => []];
            }
        }
        fclose($h);

        return $rows === []
            ? ['error' => 'Il file ha solo le intestazioni, nessun prodotto.', 'rows' => []]
            : ['error' => null, 'rows' => $rows];
    }

    private function headerKey(string $name): ?string
    {
        $n = mb_strtolower(trim($name));

        if (isset(self::ALIASES[$n])) {
            return self::ALIASES[$n];
        }
        if (preg_match('/^(?:attribute (\d+) name|nome attributo (\d+))$/u', $n, $m)) {
            return 'attr_name_' . ($m[1] !== '' ? $m[1] : $m[2]);
        }
        if (preg_match('/^(?:attribute (\d+) value\(s\)|valore\/i attributo (\d+))$/u', $n, $m)) {
            return 'attr_value_' . ($m[1] !== '' ? $m[1] : $m[2]);
        }

        return null;
    }

    /** nome minuscolo => [id, figli: nome minuscolo => id] per le categorie di primo livello. */
    private function categoryMap(): array
    {
        $all = ProductCategory::query()->get(['id', 'name', 'parent_id']);
        $map = [];
        foreach ($all->whereNull('parent_id') as $top) {
            $map[mb_strtolower($top->name)] = ['id' => $top->id, 'children' => []];
        }
        foreach ($all->whereNotNull('parent_id') as $child) {
            $parent = $all->firstWhere('id', $child->parent_id);
            if ($parent && isset($map[mb_strtolower($parent->name)])) {
                $map[mb_strtolower($parent->name)]['children'][mb_strtolower($child->name)] = $child->id;
            }
        }

        return $map;
    }

    // ── Un prodotto ──────────────────────────────────────────────────────────

    private function buildItem(array $row, array $variations, array $ctx): array
    {
        $type = $row['type'] ?? '';
        $name = trim($row['name'] ?? '');
        $errors = [];
        $warnings = [];

        if (! in_array($type, ['simple', 'variable'], true)) {
            return $this->errorItem($row, 'Tipo di prodotto "' . $type . '" non supportato: sono ammessi solo semplici e variabili.');
        }
        if ($name === '') {
            return $this->errorItem($row, 'Manca il nome del prodotto.');
        }
        if (mb_strlen($name) > 255) {
            $name = rtrim(mb_substr($name, 0, 255));
            $warnings[] = 'Nome accorciato a 255 caratteri.';
        }

        $sku = mb_substr(trim($row['sku'] ?? ''), 0, 100);

        $short = $this->plainText($row['short'] ?? '');
        if (mb_strlen($short) > 500) {
            $short = rtrim(mb_substr($short, 0, 499)) . '…';
            $warnings[] = 'Descrizione breve accorciata a 500 caratteri.';
        }
        $description = (string) RichText::clean($row['description'] ?? '');
        if (mb_strlen($description) > 20000) {
            $description = mb_substr($description, 0, 20000);
            $warnings[] = 'Descrizione accorciata a 20000 caratteri.';
        }

        $categoryId = $this->category($row['categories'] ?? '', $ctx, $warnings);

        // Quota KMoney: vuota = automatica (categoria o contratto).
        $pct = null;
        if (($row['pct'] ?? '') !== '' && is_numeric($row['pct'])) {
            if ($ctx['inDebt']) {
                $warnings[] = 'Il conto KMoney e\' in debito: la quota resta 100% e non si cambia.';
            } else {
                $snapped = KMoneyShare::snap((int) $row['pct']);
                $pct = KMoneyShare::fit($snapped, $ctx['steps']);
                if ($pct !== (int) $row['pct']) {
                    $warnings[] = 'Quota KMoney ' . (int) $row['pct'] . '% non ammessa: uso il ' . $pct . '%.';
                }
            }
        }

        $price = null;
        $sale = null;
        $stock = null;
        $variants = [];

        if ($type === 'simple') {
            $price = $this->decimal($row['price'] ?? '');
            if ($price === null) {
                $errors[] = 'Manca il prezzo (Regular price).';
            }
            $sale = $this->decimal($row['sale'] ?? '');
            if ($sale !== null && $price !== null && $sale >= $price) {
                $sale = null;
                $warnings[] = 'Prezzo in offerta non inferiore al prezzo: ignorato.';
            }
            $stock = $this->stock($row);
        } else {
            [$variants, $errs] = $this->variants($variations);
            $errors = array_merge($errors, $errs);
            if ($variants !== []) {
                $price = min(array_column($variants, 'price'));
            }
        }

        $existing = $sku !== ''
            ? Product::where('company_id', $ctx['company']->id)->where('sku', $sku)->first()
            : null;

        if ($existing && ($existing->product_type === 'variable') !== ($type === 'variable')) {
            $errors[] = 'Il prodotto esiste gia\' come ' . $existing->product_type . ': modificalo a mano.';
        }

        $data = [
            'name'                    => $name,
            'sku'                     => $sku !== '' ? $sku : null,
            'short_description'       => $short !== '' ? $short : null,
            'description'             => $description !== '' ? $description : null,
            'price'                   => $price,
            'discount_price'          => $sale,
            'kmoney_discount_percent' => $pct,
            'stock'                   => $type === 'variable' ? null : $stock,
            'weight_kg'               => $this->decimal($row['weight'] ?? ''),
            'category_id'             => $categoryId,
            'product_type'            => $type,
            'status'                  => ($row['published'] ?? '1') === '1' ? 'active' : 'inactive',
        ];

        $diff = [];
        $changed = true;
        if ($existing && $errors === []) {
            [$diff, $changed] = $this->diff($existing, $data, $variants, $pct !== null || ! $ctx['inDebt']);
        }

        $action = $errors !== [] ? 'error' : ($existing ? ($changed ? 'update' : 'same') : 'create');

        return [
            'line'        => $row['_line'] ?? 0,
            'action'      => $action,
            'name'        => $name,
            'sku'         => $sku,
            'errors'      => $errors,
            'warnings'    => $warnings,
            'diff'        => $diff,
            'data'        => $data,
            'variants'    => $variants,
            'image'       => trim(explode(',', $row['images'] ?? '')[0] ?? ''),
            'existing_id' => $existing?->id,
            'inDebt'      => $ctx['inDebt'],
        ];
    }

    private function errorItem(array $row, string $message): array
    {
        return [
            'line' => $row['_line'] ?? 0, 'action' => 'error', 'name' => $row['name'] ?? '(senza nome)',
            'sku' => $row['sku'] ?? '', 'errors' => [$message], 'warnings' => [], 'diff' => [],
            'data' => [], 'variants' => [], 'image' => '', 'existing_id' => null, 'inDebt' => false,
        ];
    }

    private function category(string $raw, array $ctx, array &$warnings): ?int
    {
        $first = trim(explode(',', $raw)[0] ?? '');
        $parts = array_values(array_filter(array_map('trim', explode('>', $first)), 'strlen'));
        if ($parts === []) {
            return null;
        }

        $top = $ctx['cats'][mb_strtolower($parts[0])] ?? null;
        if ($top === null) {
            $warnings[] = 'Categoria "' . $first . '" non trovata: il prodotto resta senza categoria.';
            return null;
        }
        if (isset($parts[1])) {
            $child = $top['children'][mb_strtolower($parts[1])] ?? null;
            if ($child !== null) {
                return $child;
            }
            $warnings[] = 'Sotto-categoria "' . $parts[1] . '" non trovata: uso solo la categoria.';
        }

        return $top['id'];
    }

    /** @return array{0: list<array>, 1: list<string>} */
    private function variants(array $rows): array
    {
        if ($rows === []) {
            return [[], ['Prodotto variabile senza variazioni nel file.']];
        }

        $out = [];
        $errors = [];
        $seen = [];

        foreach ($rows as $r) {
            $label = 'Variazione riga ' . $r['_line'];
            $names = [];
            $values = [];
            for ($i = 1; $i <= 3; $i++) {
                $n = trim($r["attr_name_{$i}"] ?? '');
                $v = trim($r["attr_value_{$i}"] ?? '');
                if ($n === '' && $v === '') {
                    continue;
                }
                if ($v === '') {
                    $errors[] = "{$label}: valore \"qualsiasi\" per \"{$n}\" non supportato.";
                    continue 2;
                }
                $names[] = $n;
                $values[] = $v;
            }
            if ($values === []) {
                $errors[] = "{$label}: nessun attributo.";
                continue;
            }

            $type = implode(' / ', $names);
            $value = implode(' / ', $values);
            if (isset($seen[mb_strtolower($type . '|' . $value)])) {
                $errors[] = "{$label}: combinazione ripetuta.";
                continue;
            }
            $seen[mb_strtolower($type . '|' . $value)] = true;

            $price = $this->decimal($r['price'] ?? '');
            if ($price === null) {
                $errors[] = "{$label}: manca il prezzo.";
                continue;
            }

            $out[] = [
                'type'  => mb_substr($type, 0, 60),
                'value' => mb_substr($value, 0, 120),
                'sku'   => mb_substr(trim($r['sku'] ?? ''), 0, 100),
                'price' => $price,
                'stock' => $this->stock($r),
            ];
        }

        return [$out, $errors];
    }

    /** @return array{0: list<string>, 1: bool} */
    private function diff(Product $p, array $d, array $variants, bool $touchesPercent): array
    {
        $lines = [];
        $changed = false;
        $eur = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $qty = fn ($q) => $q === null ? 'non gestita' : (string) $q;

        if ($p->product_type === 'variable') {
            $byKey = $p->variants()->get()->mapWithKeys(fn ($v) => [mb_strtolower($v->variant_type . '|' . $v->variant_value) => $v]);
            $nuove = 0;
            $mod = 0;
            foreach ($variants as $v) {
                $old = $byKey->get(mb_strtolower($v['type'] . '|' . $v['value']));
                if (! $old) {
                    $nuove++;
                } elseif ((float) $old->variant_price !== (float) $v['price'] || ($old->variant_stock === null || $old->variant_stock === '' ? null : (int) $old->variant_stock) !== $v['stock']) {
                    $mod++;
                }
            }
            $nuove && $lines[] = "Varianti nuove: {$nuove}";
            $mod && $lines[] = "Varianti con prezzo o giacenza diversi: {$mod}";
            $changed = $nuove > 0 || $mod > 0;
        } else {
            if ((float) $p->price !== (float) $d['price']) {
                $lines[] = 'Prezzo ' . $eur($p->price) . ' → ' . $eur($d['price']);
                $changed = true;
            }
            if ($p->stock !== $d['stock']) {
                $lines[] = 'Giacenza ' . $qty($p->stock) . ' → ' . $qty($d['stock']);
                $changed = true;
            }
            if ((float) $p->discount_price !== (float) $d['discount_price']) {
                $lines[] = 'Prezzo in offerta ' . ($p->discount_price ? $eur($p->discount_price) : 'nessuno') . ' → ' . ($d['discount_price'] ? $eur($d['discount_price']) : 'nessuno');
                $changed = true;
            }
        }

        if ($touchesPercent && $d['kmoney_discount_percent'] !== null && $p->kmoney_discount_percent !== null
            && (int) $p->kmoney_discount_percent !== $d['kmoney_discount_percent']) {
            $lines[] = 'Quota KMoney ' . (int) $p->kmoney_discount_percent . '% → ' . $d['kmoney_discount_percent'] . '%';
            $changed = true;
        } elseif ($touchesPercent && $d['kmoney_discount_percent'] !== null && $p->kmoney_discount_percent === null) {
            $lines[] = 'Quota KMoney scelta: ' . $d['kmoney_discount_percent'] . '%';
            $changed = true;
        }

        foreach (['name', 'short_description', 'description', 'category_id', 'status'] as $f) {
            if ((string) $p->{$f} !== (string) $d[$f] && ! ($f === 'category_id' && $d[$f] === null)) {
                $lines[] = 'Cambiano anche testi, categoria o stato';
                $changed = true;
                break;
            }
        }

        return [array_values(array_unique($lines)), $changed];
    }

    // ── Scrittura ────────────────────────────────────────────────────────────

    private function create(array $it, Company $company, array $opts): Product
    {
        $d = $it['data'];
        $d['company_id'] = $company->id;
        $d['slug'] = Str::slug($d['name']) . '-' . Str::lower(Str::random(5));
        if ($it['inDebt']) {
            $d['kmoney_discount_percent'] = null;
        }

        if (($opts['images'] ?? false) && $it['image'] !== '') {
            $d['featured_image'] = $this->downloadImage($it['image']);
        }

        $product = Product::create($d);
        $this->syncVariants($product, $it['variants']);

        return $product;
    }

    private function update(array $it, Company $company, array $opts): Product
    {
        $product = Product::where('company_id', $company->id)->lockForUpdate()->findOrFail($it['existing_id']);
        $d = $it['data'];

        // Quota non indicata o conto in debito: la scelta gia' fatta sul prodotto non si tocca.
        if ($it['inDebt'] || $d['kmoney_discount_percent'] === null) {
            unset($d['kmoney_discount_percent']);
        }
        // Senza categoria nel file, quella di adesso resta.
        if ($d['category_id'] === null) {
            unset($d['category_id']);
        }
        unset($d['sku']);

        if (($opts['images'] ?? false) && blank($product->featured_image) && $it['image'] !== '') {
            $d['featured_image'] = $this->downloadImage($it['image']);
        }

        $product->fill($d)->save();
        $this->syncVariants($product, $it['variants']);

        return $product;
    }

    /** Aggiorna/crea le varianti del file; quelle che il file non nomina restano. */
    private function syncVariants(Product $product, array $variants): void
    {
        if ($variants === []) {
            return;
        }

        $existing = $product->variants()->get();

        foreach ($variants as $v) {
            $match = ($v['sku'] !== '' ? $existing->firstWhere('variant_sku', $v['sku']) : null)
                ?? $existing->first(fn (ProductVariant $e) => mb_strtolower($e->variant_type . '|' . $e->variant_value) === mb_strtolower($v['type'] . '|' . $v['value']));

            $attrs = [
                'variant_type'  => $v['type'],
                'variant_value' => $v['value'],
                'variant_price' => number_format($v['price'], 2, '.', ''),
                'variant_stock' => $v['stock'],
                'variant_sku'   => $v['sku'] !== '' ? $v['sku'] : null,
            ];

            $match ? $match->update($attrs) : $product->variants()->create($attrs);
        }
    }

    /** Scarica la prima immagine e la passa da ImageStore (WebP, EXIF via). null se non va. */
    private function downloadImage(string $url): ?string
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }

        // Niente indirizzi interni: il file e' di chi lo carica, l'URL no.
        $ip = gethostbyname($parts['host']);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        try {
            $res = Http::timeout(10)->withOptions(['allow_redirects' => false])->get($url);
        } catch (\Throwable) {
            return null;
        }

        $body = $res->successful() ? $res->body() : '';
        if ($body === '' || strlen($body) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($tmp, $body);

        try {
            return (new ImageStore())->store(new UploadedFile($tmp, 'import.img', $mime, null, true), 'products', 'product', 'featured_image');
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    // ── Utilita' ─────────────────────────────────────────────────────────────

    /** "12,34" / "1.234,56" / "12.34" -> float; null se vuoto o negativo. */
    private function decimal(?string $v): ?float
    {
        $v = preg_replace('/[^0-9,.\-]/', '', trim((string) $v));
        if ($v === '' || $v === '-' || str_starts_with($v, '-')) {
            return null;
        }

        $c = strrpos($v, ',');
        $d = strrpos($v, '.');
        if ($c !== false && $d !== false) {
            $v = $c > $d ? str_replace(',', '.', str_replace('.', '', $v)) : str_replace(',', '', $v);
        } elseif ($c !== false) {
            $v = str_replace(',', '.', $v);
        }

        return round((float) $v, 2);
    }

    /** Vuoto = giacenza non gestita (sempre disponibile); "non disponibile" senza numero = 0. */
    private function stock(array $row): ?int
    {
        $s = trim($row['stock'] ?? '');
        if ($s !== '' && is_numeric(str_replace(',', '.', $s))) {
            return max(0, (int) round((float) str_replace(',', '.', $s)));
        }

        return ($row['instock'] ?? '1') === '0' ? 0 : null;
    }

    private function plainText(string $html): string
    {
        $t = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
        $t = preg_replace('#</\s*(p|div|li|h[1-6])\s*>#i', "\n", $t);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
    }
}
