<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Scarica dal sito originale le immagini che il database gia' nomina.
 *
 * Il dump porta i percorsi, non i file: banner, loghi, gallerie, foto dei
 * prodotti e dei banner pubblicitari restano rotti finche' non si copiano.
 * Qui si legge ogni percorso dal database e si chiede al vecchio sito lo
 * stesso indirizzo, salvando in `storage/app/public` con lo stesso nome.
 *
 * Si puo' rilanciare quando si vuole: i file gia' presenti si saltano,
 * a meno di --force. Chi manca sul vecchio sito viene elencato alla fine.
 */
class ImportLegacyMedia extends Command
{
    protected $signature = 'legacy:import-media
        {--from= : Indirizzo del sito originale, per esempio https://ksm.it}
        {--only= : Un solo gruppo: aziende, prodotti, pubblicita, sito}
        {--limit= : Si ferma dopo questi file}
        {--force : Riscarica anche i file presenti}
        {--cacert= : Elenco di certificati da usare, se quello di PHP e vecchio}
        {--insecure : Non verifica il certificato del sito originale}
        {--dry-run : Conta soltanto, senza scaricare}';

    protected $description = 'Scarica dal sito originale le immagini di aziende, prodotti e banner';

    /** Quante richieste insieme: il vecchio sito e' una macchina sola. */
    private const BATCH = 10;

    private const GROUPS = ['aziende', 'prodotti', 'pubblicita', 'sito'];

    /** Opzioni passate a ogni richiesta: riguardano il certificato. */
    private array $options = [];

    /** @var list<string> errori di collegamento, per spiegarli alla fine */
    private array $problems = [];

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('from') ?: env('LEGACY_MEDIA_URL', '')), '/');

        if ($base === '') {
            $this->error('Manca l\'indirizzo del sito originale.');
            $this->line('Esempio: php artisan legacy:import-media --from=https://ksm.it');

            return self::FAILURE;
        }

        $only = $this->option('only');

        if ($only && ! in_array($only, self::GROUPS, true)) {
            $this->error('Gruppi possibili: '.implode(', ', self::GROUPS).'.');

            return self::FAILURE;
        }

        $paths = $this->paths($only);

        if ($limit = (int) $this->option('limit')) {
            $paths = array_slice($paths, 0, $limit);
        }

        $this->line('File nominati dal database: '.count($paths));

        $disk = Storage::disk('public');

        $missingLocally = array_values(array_filter(
            $paths,
            fn (string $path) => $this->option('force') || ! $disk->exists($path)
        ));

        $this->line('Da scaricare: '.count($missingLocally));

        if ($this->option('dry-run') || ! $missingLocally) {
            return self::SUCCESS;
        }

        $this->options = match (true) {
            (bool) $this->option('insecure') => ['verify' => false],
            (bool) $this->option('cacert') => ['verify' => $this->option('cacert')],
            default => [],
        };

        [$saved, $absent, $failed] = $this->download($base, $missingLocally);

        $this->newLine();
        $this->info("Scaricati: $saved");

        foreach (['Assenti sul vecchio sito' => $absent, 'Non riusciti' => $failed] as $title => $list) {
            if ($list) {
                $this->warn("$title: ".count($list));
                collect($list)->take(10)->each(fn ($path) => $this->line("  $path"));
            }
        }

        $this->explainProblems();

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $paths
     * @return array{0: int, 1: list<string>, 2: list<string>}
     */
    private function download(string $base, array $paths): array
    {
        $bar = $this->output->createProgressBar(count($paths));
        $bar->start();

        $saved = 0;
        $absent = [];
        $failed = [];

        foreach (array_chunk($paths, self::BATCH) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $path) => $pool->as($path)->withOptions($this->options)->timeout(30)->get("$base/$path"),
                $chunk
            ));

            foreach ($chunk as $path) {
                $response = $responses[$path] ?? null;

                if ($response instanceof ConnectionException) {
                    $this->problems[] = $response->getMessage();
                    $failed[] = $path;
                } elseif (! $response instanceof Response) {
                    $failed[] = $path;
                } elseif ($response->notFound()) {
                    $absent[] = $path;
                } elseif ($this->store($path, $response)) {
                    $saved++;
                } else {
                    $failed[] = $path;
                }

                $bar->advance();
            }
        }

        $bar->finish();

        return [$saved, $absent, $failed];
    }

    /**
     * Il certificato scaduto di PHP e' l'inciampo piu' comune: da riga di
     * comando curl funziona e sembra un problema del vecchio sito.
     */
    private function explainProblems(): void
    {
        if (! $this->problems) {
            return;
        }

        $this->newLine();
        $this->line('Errore di collegamento: '.$this->problems[0]);

        if (str_contains(implode(' ', $this->problems), 'SSL certificate problem')) {
            $this->line('PHP non riconosce il certificato del sito. Succede con un elenco di certificati vecchio,');
            $this->line('e anche quando un antivirus (Avast, Kaspersky, ESET) rifirma le connessioni HTTPS:');
            $this->line('il suo certificato sta nell\'archivio di Windows, che PHP non legge.');
            $this->newLine();
            $this->line('Elenco dei certificati di Windows in un file, da PowerShell:');
            $this->line('  $c = Get-ChildItem Cert:\\LocalMachine\\Root, Cert:\\CurrentUser\\Root');
            $this->line('  $c | %{ "-----BEGIN CERTIFICATE-----"; [Convert]::ToBase64String($_.RawData,"InsertLineBreaks"); "-----END CERTIFICATE-----" } | Set-Content -Encoding ascii C:\\certificati.pem');
            $this->newLine();
            $this->line('Poi: php artisan legacy:import-media --from=... --cacert=C:\\certificati.pem');
            $this->line('Indicando quel file in php.ini con curl.cainfo si risolve per tutto il sito.');
        }
    }

    /** Salva solo una risposta buona: una pagina d'errore non e' un'immagine. */
    private function store(string $path, Response $response): bool
    {
        $body = $response->body();

        if (! $response->successful() || $body === '' || str_contains((string) $response->header('Content-Type'), 'text/html')) {
            return false;
        }

        return Storage::disk('public')->put($path, $body);
    }

    /**
     * Tutti i percorsi nominati dal database, una volta sola.
     *
     * @return list<string>
     */
    private function paths(?string $only): array
    {
        $paths = [];

        $add = function ($value) use (&$paths) {
            // Le colonne di galleria tengono un elenco json, le altre un percorso.
            foreach ((array) (is_string($value) && str_starts_with($value, '[') ? json_decode($value, true) : $value) as $path) {
                $path = trim((string) $path, "/ \t");

                // Solo dentro `uploads`: niente percorsi che risalgono le cartelle.
                if (str_starts_with($path, 'uploads/') && ! str_contains($path, '..')) {
                    $paths[$path] = true;
                }
            }
        };

        if (! $only || $only === 'aziende') {
            DB::table('companies')->select('banner', 'logo', 'offer_gallery')
                ->where(fn ($q) => $q->whereNotNull('banner')->orWhereNotNull('logo')->orWhereNotNull('offer_gallery'))
                ->orderBy('id')
                ->chunk(1000, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        $add([$row->banner, $row->logo]);
                        $add($row->offer_gallery);
                    }
                });
        }

        if (! $only || $only === 'prodotti') {
            DB::table('products')->select('featured_image', 'gallery_images')
                ->where(fn ($q) => $q->whereNotNull('featured_image')->orWhereNotNull('gallery_images'))
                ->orderBy('id')
                ->chunk(1000, function ($rows) use ($add) {
                    foreach ($rows as $row) {
                        $add([$row->featured_image]);
                        $add($row->gallery_images);
                    }
                });

            DB::table('product_variants')->select('variant_images')
                ->whereNotNull('variant_images')
                ->orderBy('id')
                ->chunk(1000, fn ($rows) => $rows->each(fn ($row) => $add($row->variant_images)));
        }

        if (! $only || $only === 'pubblicita') {
            DB::table('advertisements')->whereNotNull('img')->orderBy('id')
                ->chunk(1000, fn ($rows) => $rows->each(fn ($row) => $add([$row->img])));
        }

        if (! $only || $only === 'sito') {
            $settings = DB::table('admin_settings')->select('site_logo', 'favicon')->first();
            $add([$settings->site_logo ?? null, $settings->favicon ?? null]);
        }

        return array_keys($paths);
    }
}
