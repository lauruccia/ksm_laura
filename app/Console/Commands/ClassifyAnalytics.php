<?php

namespace App\Console\Commands;

use App\Support\Analytics\Humans;
use App\Support\Analytics\Period;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Contrassegna le visite che sembrano di programmi (colonna page_views.is_bot).
 *
 * Gira ogni ora sugli ultimi giorni e, a ogni giro, recupera anche un tratto
 * di storico non ancora esaminato, andando all'indietro: cosi' le righe
 * vecchie si ripuliscono da sole, senza terminale.
 */
class ClassifyAnalytics extends Command
{
    /** Giorni dello storico esaminati a ogni giro, oltre agli ultimi. */
    private const BACKLOG_DAYS = 10;

    /** Per quanto tempo una visita senza movimenti puo' ancora ricevere il suo tempo di lettura. */
    private const SETTLE_HOURS = 2;

    protected $signature = 'analytics:classify {--days=3 : Giorni recenti da esaminare} {--no-backlog : Salta il recupero dello storico}';

    protected $description = 'Contrassegna le visite delle statistiche che sembrano di programmi';

    public function handle(): int
    {
        $today = Period::today();
        $recent = max(1, (int) $this->option('days'));
        $settled = now(config('ksm.analytics.timezone'))->subHours(self::SETTLE_HOURS);

        $marked = Humans::classify(new Period($today->subDays($recent - 1), $today, 'recenti'), $settled);

        if (! $this->option('no-backlog')) {
            $marked += $this->backlog($today->subDays($recent), $settled);
        }

        $this->info("Visite contrassegnate come non umane: $marked.");

        return self::SUCCESS;
    }

    /** Uno scaglione di storico piu' vecchio dei giorni recenti, dal piu' nuovo al piu' vecchio. */
    private function backlog(CarbonImmutable $start, $settled): int
    {
        $oldest = DB::table('page_views')->min('day');

        if ($oldest === null) {
            return 0;
        }

        // Il giorno piu' vecchio gia' esaminato resta in cache: si riprende dal giorno prima.
        $marker = Cache::get('analytics:classified-down-to');
        $to = $marker ? CarbonImmutable::parse($marker, config('ksm.analytics.timezone'))->startOfDay()->subDay() : $start;

        if ($to->toDateString() < $oldest) {
            return 0;
        }

        $from = max($to->subDays(self::BACKLOG_DAYS - 1), CarbonImmutable::parse($oldest, config('ksm.analytics.timezone'))->startOfDay());
        $marked = Humans::classify(new Period($from, $to, 'storico'), $settled);

        Cache::forever('analytics:classified-down-to', $from->toDateString());

        return $marked;
    }
}
