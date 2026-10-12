<?php

namespace App\Support\Analytics;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * "Traffico probabilmente umano": quali visite tenere quando si nascondono
 * quelle che sembrano di programmi automatici.
 *
 * Nessun filtro e' una prova. Una visita e' sospetta se:
 *  - il browser non ha mai comunicato un tempo di permanenza (somma durata
 *    zero): chi legge davvero una pagina lo comunica quando la lascia, un
 *    programma che non esegue gli script no;
 *  - ha oltre MAX_PAGES pagine: scansioni del sito, non lettura;
 *  - arriva da un paese in cui quasi nessuna visita legge la pagina (sotto
 *    MIN_ENGAGED_SHARE, con almeno MIN_SESSIONS visite): tipico dei data
 *    center. L'elenco si ricava dai dati del periodo, non e' fisso, e l'Italia
 *    non vi entra mai.
 *
 * Il prezzo: una persona con un browser che non comunica la durata (alcuni
 * blocchi di script, chiusure brusche su telefono) viene nascosta anche lei.
 */
final class Humans
{
    public const MAX_PAGES = 40;

    public const MIN_SESSIONS = 100;

    public const MIN_ENGAGED_SHARE = 0.05;

    /** @var array<string, list<string>> */
    private static array $countries = [];

    /** Dimentica i paesi gia' calcolati (servono ai test: in produzione la memoria vale una richiesta). */
    public static function forget(): void
    {
        self::$countries = [];
    }

    private static function scope(Period $period, ?string $host): Builder
    {
        return DB::table('page_views')
            ->whereBetween('day', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($host, fn (Builder $query) => $query->where('host', $host));
    }

    /**
     * I paesi da cui, nel periodo, quasi nessuna visita ha letto la pagina.
     *
     * @return list<string>
     */
    public static function suspectCountries(Period $period, ?string $host): array
    {
        $key = $period->from->toDateString().'|'.$period->to->toDateString().'|'.$host;

        if (isset(self::$countries[$key])) {
            return self::$countries[$key];
        }

        $perSession = self::scope($period, $host)
            ->selectRaw('session_id, case when sum(duration) > 0 then 1 else 0 end as engaged')
            ->groupBy('session_id');

        $rows = self::scope($period, $host)->where('is_entry', true)->whereNotNull('country')
            ->joinSub($perSession, 's', 's.session_id', '=', 'page_views.session_id')
            ->selectRaw('country, count(*) as total, sum(s.engaged) as engaged')
            ->groupBy('country')
            ->havingRaw('count(*) >= ?', [self::MIN_SESSIONS])
            ->get();

        return self::$countries[$key] = $rows
            ->filter(fn ($row) => $row->country !== 'IT' && ((int) $row->engaged) / max(1, (int) $row->total) < self::MIN_ENGAGED_SHARE)
            ->pluck('country')->values()->all();
    }

    /** Le visite (session_id) da tenere nel periodo, come sottoquery per un whereIn. */
    public static function sessions(Period $period, ?string $host): Builder
    {
        $query = self::scope($period, $host)->select('session_id')->groupBy('session_id')
            ->havingRaw('sum(duration) > 0')
            ->havingRaw('count(*) <= ?', [self::MAX_PAGES]);

        if ($countries = self::suspectCountries($period, $host)) {
            $marks = implode(',', array_fill(0, count($countries), '?'));
            $query->havingRaw("coalesce(max(case when is_entry = 1 then country end), '') not in ($marks)", $countries);
        }

        return $query;
    }

    /**
     * Quante visite ci sono e quante verrebbero nascoste, per dirlo in pagina.
     *
     * @return array{total: int, hidden: int, share: float, countries: list<string>}
     */
    public static function summary(Period $period, ?string $host): array
    {
        $total = (int) self::scope($period, $host)->where('is_entry', true)->count();
        $kept = $total > 0 ? (int) DB::query()->fromSub(self::sessions($period, $host), 'h')->count() : 0;
        $hidden = max(0, $total - $kept);

        return [
            'total' => $total,
            'hidden' => $hidden,
            'share' => $total > 0 ? $hidden / $total : 0.0,
            'countries' => self::suspectCountries($period, $host),
        ];
    }
}
