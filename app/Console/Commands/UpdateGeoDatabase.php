<?php

namespace App\Console\Commands;

use App\Support\Analytics\Geo;
use App\Support\Analytics\MmdbReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Scarica l'archivio gratuito DB-IP City Lite (CC BY 4.0) che permette di
 * sapere regione e citta' dei visitatori. Si lancia a mano, di rado (l'archivio
 * esce ogni mese): non e' nel calendario perche' pesa decine di MB e su un
 * hosting condiviso potrebbe superare i limiti. Se il server non riesce, si puo'
 * scaricare il file .mmdb.gz da db-ip.com e caricarlo con cPanel in
 * storage/app/geoip/dbip-city-lite.mmdb.
 */
class UpdateGeoDatabase extends Command
{
    protected $signature = 'analytics:geo-update {--mese= : Mese da scaricare, AAAA-MM (predefinito: questo, poi il precedente)}';

    protected $description = 'Scarica o aggiorna l\'archivio geografico DB-IP City Lite per regioni e citta\'';

    public function handle(): int
    {
        $target = (string) config('ksm.analytics.geoip_database');

        if ($target === '') {
            $this->error('Nessun percorso in KSM_GEOIP_DATABASE.');

            return self::FAILURE;
        }

        @mkdir(dirname($target), 0755, true);

        $months = $this->option('mese') ? [(string) $this->option('mese')] : [now()->format('Y-m'), now()->subMonthNoOverflow()->format('Y-m')];

        foreach ($months as $month) {
            $url = "https://download.db-ip.com/free/dbip-city-lite-{$month}.mmdb.gz";
            $this->line("Scarico {$url}");

            $gz = $target.'.gz.part';
            $out = $target.'.part';

            try {
                $response = Http::timeout(600)->withOptions(['sink' => $gz])->get($url);
            } catch (\Throwable $e) {
                $this->warn('Non riuscito: '.$e->getMessage());
                @unlink($gz);

                continue;
            }

            if (! $response->successful()) {
                $this->warn('Non disponibile (HTTP '.$response->status().').');
                @unlink($gz);

                continue;
            }

            // Si decomprime a blocchi: il file intero non entra in memoria.
            $in = gzopen($gz, 'rb');
            $dst = fopen($out, 'wb');

            if (! $in || ! $dst) {
                $this->error('Impossibile scrivere in '.dirname($target));

                return self::FAILURE;
            }

            while (! gzeof($in)) {
                fwrite($dst, (string) gzread($in, 1 << 20));
            }

            gzclose($in);
            fclose($dst);
            @unlink($gz);

            try {
                new MmdbReader($out);
            } catch (\Throwable $e) {
                @unlink($out);
                $this->error('Il file scaricato non e\' un archivio valido: '.$e->getMessage());

                return self::FAILURE;
            }

            rename($out, $target);
            Geo::forget();
            $this->info("Archivio aggiornato ({$month}): ".round(filesize($target) / 1048576, 1).' MB.');

            return self::SUCCESS;
        }

        $this->error('Nessun archivio scaricato. Scaricalo da db-ip.com e caricalo in '.$target);

        return self::FAILURE;
    }
}
