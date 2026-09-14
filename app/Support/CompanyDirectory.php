<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Ordina la directory delle aziende.
 *
 * Prima le fasce, dal piano piu' ricco al piu' economico; dentro ogni
 * fascia l'ordine e' casuale e cambia a ogni visita. La fascia invece
 * non cambia mai: chi paga di piu' resta davanti.
 *
 * Filtro, ordine e paginazione li fa il database: a PHP arrivano gli id
 * della pagina e poi solo quelle aziende, anche con centomila schede.
 * Il mescolamento e' una chiave calcolata dal seme con sola aritmetica
 * intera, che SQLite e MySQL eseguono allo stesso modo: stesso seme,
 * stesso ordine, quindi le pagine successive restano coerenti con la prima.
 */
class CompanyDirectory
{
    public const PER_PAGE = 12;

    /** Modulo della chiave di mescolamento: il primo 2^31 - 1. */
    private const MODULUS = 2147483647;

    /** Un seme nuovo a ogni visita, oppure quello che arriva dalla paginazione. */
    public static function seed(?int $requested): int
    {
        return $requested > 0 ? $requested : random_int(1, 999_999);
    }

    public function paginate(Builder $filtered, int $seed, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $page = max(1, Paginator::resolveCurrentPage());
        $total = (clone $filtered)->toBase()->getCountForPagination();

        // Oltre l'ultima pagina non serve nemmeno ordinare.
        $ids = $total > ($page - 1) * $perPage
            ? $this->orderedIds($filtered, $seed)->forPage($page, $perPage)->pluck('companies.id')->all()
            : [];

        return new LengthAwarePaginator(
            $this->hydrate($ids),
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    /** Le prime del mazzo, stesso ordine: serve alla vetrina della home. */
    public function take(Builder $filtered, int $seed, int $limit): Collection
    {
        return collect($this->hydrate(
            $this->orderedIds($filtered, $seed)->limit($limit)->pluck('companies.id')->all()
        ));
    }

    /** Gli id nell'ordine in cui vanno mostrati. Il limite lo mette chi chiama. */
    private function orderedIds(Builder $filtered, int $seed): QueryBuilder
    {
        return (clone $filtered)
            ->toBase()
            ->leftJoin('plans', 'plans.id', '=', 'companies.plan_id')
            ->reorder()
            // Le aziende senza piano in fondo, detto esplicitamente invece di
            // affidarsi a dove ciascun database mette i NULL.
            ->orderByRaw('case when plans.id is null then 1 else 0 end')
            ->orderByDesc('plans.priority')
            ->orderByDesc('plans.price')
            ->orderByRaw($this->shuffleKey('companies.id', $seed))
            // La chiave puo' ripetersi: l'id rende l'ordine totale, cosi'
            // nessuna azienda salta o si ripete da una pagina all'altra.
            ->orderBy('companies.id');
    }

    /**
     * Chiave di mescolamento, identica su SQLite e MySQL.
     *
     * Solo interi: prodotto e resto modulo 2^31 - 1, uno xorshift, un secondo
     * prodotto. Lo XOR e' scritto (a|b) - (a&b) perche' SQLite non ha
     * l'operatore. Nessun passaggio supera 2^63, quindi niente overflow.
     * Moltiplicatore e scarto vengono dal seme passato per crc32: semi vicini
     * danno ordini che non si somigliano. Nella query finiscono solo interi
     * calcolati qui, mai testo della richiesta.
     */
    private function shuffleKey(string $column, int $seed): string
    {
        $m = self::MODULUS;
        $multiplier = crc32("a:$seed") % ($m - 1) + 1;
        $offset = crc32("b:$seed") % ($m - 1) + 1;

        $x = "((($column % $m) * $multiplier + $offset) % $m)";
        $shifted = "($x >> 16)";

        return "(((($x | $shifted) - ($x & $shifted)) * 48271) % $m)";
    }

    private function hydrate(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $positions = array_flip($ids);

        return Company::query()
            ->whereIn('companies.id', $ids)
            ->with('category', 'plan')
            ->withAvg('reviews', 'rating')
            ->get()
            ->sortBy(fn ($company) => $positions[$company->id])
            ->values()
            ->all();
    }
}
