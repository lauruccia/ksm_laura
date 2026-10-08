<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cancella le visite piu' vecchie del tempo di conservazione.
 *
 * Senza questo giro la tabella crescerebbe per sempre: con decine di
 * domini sono molte righe, e le statistiche oltre l'anno servono di rado.
 */
class PruneAnalytics extends Command
{
    protected $signature = 'analytics:prune {--days= : Giorni da conservare (di serie KSM_ANALYTICS_RETENTION_DAYS)}';

    protected $description = 'Cancella le visite delle statistiche piu\' vecchie del tempo di conservazione';

    public function handle(): int
    {
        $days = max(31, (int) ($this->option('days') ?: config('ksm.analytics.retention_days')));
        $cutoff = now(config('ksm.analytics.timezone'))->subDays($days)->toDateString();

        $deleted = DB::table('page_views')->where('day', '<', $cutoff)->delete();
        DB::table('conversions')->where('day', '<', $cutoff)->delete();

        $this->info("Visite cancellate: $deleted (precedenti al $cutoff).");

        return self::SUCCESS;
    }
}
