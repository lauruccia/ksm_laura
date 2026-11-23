<?php

namespace App\Console\Commands;

use App\Support\Analytics\Humans;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cancella le visite piu' vecchie del tempo di conservazione.
 *
 * Senza questo giro la tabella crescerebbe per sempre: con decine di
 * domini sono molte righe, e le statistiche oltre l'anno servono di rado.
 */
class PruneAnalytics extends Command
{
    /** Giorni di storico ripuliti dalle visite non umane a ogni giro: il resto si recupera nelle notti dopo. */
    private const BOT_DAYS_PER_RUN = 30;

    protected $signature = 'analytics:prune {--days= : Giorni da conservare (di serie KSM_ANALYTICS_RETENTION_DAYS)} {--bots-days= : Giorni dopo i quali si cancellano le visite non umane (di serie KSM_ANALYTICS_BOT_RETENTION_DAYS, 0 = mai)}';

    protected $description = 'Cancella le visite delle statistiche piu\' vecchie del tempo di conservazione';

    public function handle(): int
    {
        $days = max(31, (int) ($this->option('days') ?: config('ksm.analytics.retention_days')));
        $cutoff = now(config('ksm.analytics.timezone'))->subDays($days)->toDateString();

        $deleted = DB::table('page_views')->where('day', '<', $cutoff)->delete();
        DB::table('conversions')->where('day', '<', $cutoff)->delete();

        $this->info("Visite cancellate: $deleted (precedenti al $cutoff).");

        $botDays = (int) ($this->option('bots-days') ?? config('ksm.analytics.bot_retention_days'));

        if ($botDays > 0) {
            $this->info('Visite non umane cancellate: '.$this->pruneBots($botDays).'.');
        }

        return self::SUCCESS;
    }

    /**
     * Cancella le visite che sembrano di programmi (nessun tempo di lettura
     * in tutta la visita, o oltre MAX_PAGES pagine in un giorno) piu' vecchie
     * di $days giorni. Sono la gran parte delle righe e rallentano ogni
     * conteggio. Un giorno alla volta, e un tot di giorni a ogni giro:
     * il punto raggiunto resta in cache.
     */
    private function pruneBots(int $days): int
    {
        $limit = now(config('ksm.analytics.timezone'))->subDays($days)->toDateString();
        $done = Cache::get('analytics:bots-pruned-until');

        $dates = DB::table('page_views')
            ->where('day', '<', $limit)
            ->when($done, fn ($query) => $query->where('day', '>', $done))
            ->distinct()->orderBy('day')->limit(self::BOT_DAYS_PER_RUN)->pluck('day');

        $deleted = 0;

        foreach ($dates as $day) {
            $bots = DB::table('page_views')->where('day', $day)->select('session_id')->groupBy('session_id')
                ->havingRaw('sum(duration) = 0 or count(*) > ?', [Humans::MAX_PAGES])
                ->pluck('session_id');

            foreach ($bots->chunk(500) as $chunk) {
                $deleted += DB::table('page_views')->where('day', $day)->whereIn('session_id', $chunk->all())->delete();
            }

            Cache::forever('analytics:bots-pruned-until', (string) $day);
        }

        return $deleted;
    }
}
