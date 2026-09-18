<?php

namespace App\Console\Commands;

use App\Support\Legacy\LegacyTestData;
use App\Support\Legacy\MysqlDumpReader;
use App\Support\PlanCapabilities as C;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Porta nel database locale aziende e prodotti del sito originale.
 *
 * Legge il dump di phpMyAdmin riga per riga e scrive nel database
 * configurato tenendo gli id originali, cosi' utenti, aziende e prodotti
 * restano collegati come lo erano. Per questo parte solo su tabelle vuote:
 * prima si lancia `php artisan migrate:fresh`.
 *
 * Entrano piani, categorie, marchi, utenti, aziende, prodotti e varianti,
 * tranne i dati di prova elencati in `LegacyTestData`.
 * Il resto resta fuori: ordini, sessioni, impostazioni con le chiavi dei
 * gestori di pagamento, tabelle di appoggio dell'import da WordPress.
 */
class ImportLegacyDump extends Command
{
    protected $signature = 'legacy:import
        {file=database/ksmdb_databaseoriginale.sql : Percorso del dump}
        {--chunk=500 : Righe scritte per ogni insert}';

    protected $description = 'Importa aziende e prodotti dal dump del database originale';

    private const TABLES = [
        'plans', 'company_categories', 'product_categories', 'product_brands',
        'users', 'companies', 'products', 'product_variants',
    ];

    /** Voci del vecchio piano => cosa concedono qui. */
    private const FEATURE_CAPABILITIES = [
        'COMPANY NAME' => [C::DIRECTORY],
        'ADDRESS' => [C::CONTACT_CARD],
        'PHONE' => [C::CONTACT_CARD],
        'E-MAIL' => [C::CONTACT_CARD],
        'LOGO' => [C::LOGO],
        'BANNER' => [C::BANNER],
        'WORKING HOURS' => [C::SHOWCASE],
        'OFFER GALLERY' => [C::GALLERY],
        'PERSONAL PAGE' => [C::SHOWCASE, C::DESCRIPTION],
        'E-COMMERCE' => [C::SHOP],
    ];

    /** Piani del vecchio sito che non scadono: gli altri durano un anno. */
    private const LIFETIME_PLANS = ['ecommerce', 'vetrina', 'biglietto'];

    /** Nomi bilingui del vecchio sito => nome in `ksm.regions`. */
    private const REGION_ALIASES = [
        'Trentino-Alto Adige/Südtirol' => 'Trentino-Alto Adige',
        "Valle d'Aosta/Vallée d'Aoste" => "Valle d'Aosta",
    ];

    private array $columns = [];

    private array $buffers = [];

    private array $counts = [];

    private int $withoutRegion = 0;

    private int $orphanVariants = 0;

    /** @var array{companies: int, users: int, products: int, brands: int} */
    private array $testData = ['companies' => 0, 'users' => 0, 'products' => 0, 'brands' => 0];

    public function handle(): int
    {
        $path = $this->argument('file');
        $path = is_file($path) ? $path : base_path($path);

        if (! is_file($path)) {
            $this->error("Dump non trovato: $path");

            return self::FAILURE;
        }

        $occupied = array_filter(self::TABLES, fn ($table) => DB::table($table)->exists());

        if ($occupied) {
            $this->error('Tabelle gia\' piene: '.implode(', ', $occupied).'.');
            $this->line('Gli id originali andrebbero in conflitto. Lancia prima: php artisan migrate:fresh');

            return self::FAILURE;
        }

        foreach (self::TABLES as $table) {
            $this->columns[$table] = array_flip(Schema::getColumnListing($table));
            $this->counts[$table] = 0;
        }

        $started = microtime(true);
        $superAdmin = DB::table('roles')->where('slug', 'super-admin')->value('id');
        $plans = [];

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($path, $superAdmin, &$plans) {
                foreach ((new MysqlDumpReader($path))->rows(self::TABLES) as [$table, $row]) {
                    match ($table) {
                        // I piani sono pochi: servono tutti per girare l'ordine.
                        'plans' => $plans[] = $row,
                        'users' => $this->push($table, $this->user($row, $superAdmin)),
                        'companies' => $this->push($table, $this->company($row)),
                        'products' => $this->push($table, $this->product($row)),
                        'product_variants' => $this->push($table, $this->variant($row)),
                        default => $this->push($table, $row),
                    };
                }

                $this->writePlans($plans);

                foreach (array_keys($this->buffers) as $table) {
                    $this->flush($table);
                }

                // Aziende, prodotti e marche inventati per provare il vecchio sito.
                $this->testData = (new LegacyTestData())->purge();
                $this->counts['companies'] -= $this->testData['companies'];
                $this->counts['users'] -= $this->testData['users'];
                $this->counts['products'] -= $this->testData['products'];
                $this->counts['product_brands'] -= $this->testData['brands'];

                // Nel vecchio database restavano varianti di prodotti gia' cancellati.
                $this->orphanVariants = DB::table('product_variants')
                    ->whereNotIn('product_id', DB::table('products')->select('id'))
                    ->delete();
                $this->counts['product_variants'] -= $this->orphanVariants;

                $this->counts['company_subscriptions'] = $this->createSubscriptions();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->table(['Tabella', 'Righe importate'], collect($this->counts)->map(fn ($n, $t) => [$t, number_format($n, 0, ',', '.')])->values());
        $this->line("Aziende senza regione riconosciuta: {$this->withoutRegion}");
        $this->line("Varianti scartate perche' il prodotto non esiste piu': {$this->orphanVariants}");
        $this->line(vsprintf('Dati di prova tolti: aziende %d, utenti %d, prodotti %d, marche %d', $this->testData));
        $this->checkForeignKeys();

        // Domini, pagine e banner hanno un comando loro, lanciabile anche dopo.
        $this->call('legacy:import-content', ['file' => $path]);
        $this->info(sprintf('Import completato in %.0f secondi.', microtime(true) - $started));

        return self::SUCCESS;
    }

    private function user(array $row, ?int $superAdmin): array
    {
        return [
            // I token "ricordami" del vecchio sito non devono aprire sessioni qui.
            'remember_token' => null,
            'is_active' => true,
            // Come la migrazione dei ruoli: chi era amministratore resta tale.
            'role_id' => $row['user_type'] === 'admin' ? $superAdmin : null,
        ] + $row;
    }

    private function company(array $row): array
    {
        $location = $row['company_location'] ?: $row['address'];
        $segments = array_values(array_filter(array_map('trim', explode(',', (string) $location))));

        $region = null;
        foreach ($segments as $segment) {
            $segment = self::REGION_ALIASES[$segment] ?? $segment;
            if (in_array($segment, config('ksm.regions'), true)) {
                $region = $segment;
                break;
            }
        }

        if ($region === null) {
            $this->withoutRegion++;
        }

        return [
            'region' => $region,
            // Il vecchio sito lasciava vuota la citta' e la scriveva in fondo alla localita'.
            'city' => filled($row['city']) ? $row['city'] : (end($segments) ?: null),
            'force_www' => false,
            'working_hours' => $this->json($row['working_hours']),
            'offer_gallery' => $this->json($row['offer_gallery']),
        ] + $row;
    }

    private function product(array $row): array
    {
        return [
            'stock' => $row['stock'] === null ? null : (int) $row['stock'],
            'product_type' => in_array($row['product_type'], ['variant', 'variable'], true) ? 'variable' : 'simple',
        ] + $row;
    }

    /** Il vecchio schema teneva solo `attributes`: tipo e valore si ricavano da li'. */
    private function variant(array $row): array
    {
        $attributes = json_decode((string) $row['attributes'], true);

        return is_array($attributes) && $attributes ? [
            'variant_type' => implode(' / ', array_keys($attributes)),
            'variant_value' => implode(' / ', array_values($attributes)),
        ] + $row : $row;
    }

    private function writePlans(array $plans): void
    {
        // Nel vecchio sito priority 1 era il piano piu' ricco; qui la
        // directory mette in cima il numero piu' alto.
        $top = max(array_map(fn ($plan) => (int) $plan['priority'], $plans ?: [['priority' => 0]])) + 1;

        foreach ($plans as $plan) {
            $features = json_decode((string) $plan['features'], true) ?: [];
            $capabilities = collect($features)
                ->flatMap(fn ($feature) => self::FEATURE_CAPABILITIES[strtoupper(trim($feature))] ?? [])
                ->all();

            $this->push('plans', [
                'priority' => $top - (int) $plan['priority'],
                'features' => json_encode(array_values($features)),
                'capabilities' => json_encode(C::sanitize($capabilities)),
                'duration_days' => in_array($plan['slug'], self::LIFETIME_PLANS, true) ? null : 365,
                'is_active' => true,
            ] + $plan);
        }
    }

    /** Un periodo attivo per ogni azienda con piano, dalla sua data di creazione. */
    private function createSubscriptions(): int
    {
        $plans = DB::table('plans')->get(['id', 'price', 'duration_days'])->keyBy('id');
        $now = now();
        $rows = [];
        $created = 0;

        $companies = DB::table('companies')->whereNotNull('plan_id')->select('id', 'plan_id', 'created_at');

        foreach ($companies->lazyById(2000) as $company) {
            $plan = $plans[$company->plan_id] ?? null;
            $startsAt = $company->created_at ? Carbon::parse($company->created_at) : $now;

            $rows[] = [
                'company_id' => $company->id,
                'plan_id' => $company->plan_id,
                'status' => 'active',
                'price' => $plan->price ?? 0,
                'currency' => config('ksm.currency', 'EUR'),
                'starts_at' => $startsAt,
                'ends_at' => $plan && $plan->duration_days === null
                    ? null
                    : $startsAt->copy()->addDays((int) ($plan->duration_days ?? 365)),
                'notes' => 'Importato dal database originale',
                // Aziende inserite da Gruppo Kosmos: nessun invito a rinnovare.
                'send_reminders' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= (int) $this->option('chunk')) {
                DB::table('company_subscriptions')->insert($rows);
                $created += count($rows);
                $rows = [];
            }
        }

        if ($rows) {
            DB::table('company_subscriptions')->insert($rows);
            $created += count($rows);
        }

        return $created;
    }

    private function push(string $table, array $row): void
    {
        // Solo le colonne che esistono qui: quelle del vecchio schema
        // senza corrispondente (stato SSL, errori dominio) si perdono.
        $this->buffers[$table][] = array_intersect_key($row, $this->columns[$table]);

        if (count($this->buffers[$table]) >= (int) $this->option('chunk')) {
            $this->flush($table);
        }
    }

    private function flush(string $table): void
    {
        if (empty($this->buffers[$table])) {
            return;
        }

        DB::table($table)->insert($this->buffers[$table]);

        $before = $this->counts[$table];
        $this->counts[$table] += count($this->buffers[$table]);
        $this->buffers[$table] = [];

        if (intdiv($this->counts[$table], 20000) > intdiv($before, 20000)) {
            $this->line("  $table: ".number_format($this->counts[$table], 0, ',', '.'));
        }
    }

    /** JSON normalizzato. Alcuni orari erano salvati due volte, come stringa che contiene JSON. */
    private function json(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    private function checkForeignKeys(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $broken = DB::select('PRAGMA foreign_key_check');

        $broken
            ? $this->warn('Riferimenti non validi: '.count($broken).' (PRAGMA foreign_key_check).')
            : $this->line('Riferimenti tra tabelle: tutti validi.');
    }
}
