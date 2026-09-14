<?php

namespace App\Console\Commands;

use App\Support\Legacy\MysqlDumpReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Porta dal sito originale domini della rete, pagine CMS e campagne banner.
 *
 * Sono poche righe e non dipendono da utenti o aziende: si possono
 * importare anche in un database gia' popolato da `legacy:import`, purche'
 * queste tre tabelle siano vuote, perche' gli id restano quelli originali.
 */
class ImportLegacyContent extends Command
{
    protected $signature = 'legacy:import-content
        {file=database/ksmdb_databaseoriginale.sql : Percorso del dump}';

    protected $description = 'Importa domini, pagine CMS e campagne banner dal dump del database originale';

    private const TABLES = ['domains', 'cms_pages', 'advertisements'];

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
            $this->line('Le righe tengono gli id originali: queste tabelle devono essere vuote.');

            return self::FAILURE;
        }

        $columns = collect(self::TABLES)->mapWithKeys(fn ($table) => [
            $table => array_flip(Schema::getColumnListing($table)),
        ]);
        $companyCategories = DB::table('company_categories')->pluck('id')->flip();
        $productCategories = DB::table('product_categories')->pluck('id')->flip();
        $counts = array_fill_keys(self::TABLES, 0);

        DB::transaction(function () use ($path, $columns, $companyCategories, $productCategories, &$counts) {
            foreach ((new MysqlDumpReader($path))->rows(self::TABLES) as [$table, $row]) {
                $row = match ($table) {
                    'domains' => $this->domain($row, $companyCategories->all(), $productCategories->all()),
                    'advertisements' => $this->advertisement($row),
                    default => $row,
                };

                // Le colonne del vecchio schema senza corrispondente, come lo stato SSL, si perdono.
                DB::table($table)->insert(array_intersect_key($row, $columns[$table]));
                $counts[$table]++;
            }
        });

        $this->table(['Tabella', 'Righe importate'], collect($counts)->map(fn ($n, $table) => [$table, $n])->values());
        $this->line('Loghi dei domini, immagini delle pagine e dei banner restano in uploads/: vanno copiati a parte.');
        $this->line('Per lo stato di collegamento dei domini: php artisan domains:check');

        return self::SUCCESS;
    }

    /** Una categoria che non esiste piu' si perde: meglio un dominio senza filtro che un riferimento rotto. */
    private function domain(array $row, array $companyCategories, array $productCategories): array
    {
        return [
            'company_category_id' => $row['company_category_id'] !== null && isset($companyCategories[$row['company_category_id']])
                ? $row['company_category_id']
                : null,
            'product_category_id' => $row['product_category_id'] !== null && isset($productCategories[$row['product_category_id']])
                ? $row['product_category_id']
                : null,
        ] + $row;
    }

    /** Le campagne del sito originale non avevano date ne' limiti: restano a periodo, senza scadenza. */
    private function advertisement(array $row): array
    {
        return ['billing' => 'period', 'impressions' => 0] + $row;
    }
}
