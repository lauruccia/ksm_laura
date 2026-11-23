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
     * Cancella le visite contrassegnate come non umane (analytics:classify)
     * piu' vecchie di $days giorni. Sono la gran parte delle righe e rallentano
     * ogni conteggio. Un giorno alla volta e un tot di giorni a ogni giro: i
     * giorni gia' puliti non compaiono piu', il resto si recupera nelle notti dopo.
     */
    private function pruneBots(int $days): int
    {
        $limit = now(config('ksm.analytics.timezone'))->subDays($days)->toDateString();

        $dates = DB::table('page_views')
            ->where('day', '<', $limit)->where('is_bot', true)
            ->distinct()->orderBy('day')->limit(self::BOT_DAYS_PER_RUN)->pluck('day');

        $deleted = 0;

        foreach ($dates as $day) {
            $deleted += DB::table('page_views')->where('day', $day)->where('is_bot', true)->delete();
        }

        return $deleted;
    }
}
