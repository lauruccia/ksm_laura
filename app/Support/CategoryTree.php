<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Albero di una tabella di categorie legate da `parent_id`.
 *
 * La tabella si legge una volta sola, sono poche centinaia di righe:
 * livelli, percorsi e rami si calcolano poi in memoria, senza una query
 * per ogni gradino. Un giro chiuso rimasto nei dati vecchi non manda in
 * loop nessun metodo.
 */
final class CategoryTree
{
    /** @var array<int, string> */
    private array $names = [];

    /** @var array<int, int|null> */
    private array $parents = [];

    /** @var array<int, list<int>> */
    private array $children = [];

    /**
     * L'albero di una tabella, letto una volta sola per richiesta.
     *
     * Filtro del dominio, riquadri, menu e banner lo chiedono piu' volte
     * nella stessa pagina. Resta nel contenitore dell'applicazione, che
     * nasce a ogni richiesta, e si butta quando una categoria cambia.
     *
     * @param  class-string<Model>  $model
     */
    public static function of(string $model): self
    {
        $key = self::containerKey($model);

        if (! app()->bound($key)) {
            app()->instance($key, new self($model::query()->get(['id', 'name', 'parent_id'])));
        }

        return app($key);
    }

    /** @param  class-string<Model>  $model */
    public static function forget(string $model): void
    {
        app()->forgetInstance(self::containerKey($model));
    }

    private static function containerKey(string $model): string
    {
        return 'category-tree:'.$model;
    }

    /** @param  iterable<object>  $rows  righe con id, name e parent_id */
    public function __construct(iterable $rows)
    {
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $this->names[$id] = (string) $row->name;
            $this->parents[$id] = $row->parent_id ? (int) $row->parent_id : null;
        }

        foreach ($this->parents as $id => $parent) {
            if ($parent !== null) {
                $this->children[$parent][] = $id;
            }
        }
    }

    /**
     * La categoria e le sue madri, dalla categoria alla radice.
     *
     * @return list<int>
     */
    public function lineage(int $id): array
    {
        $ids = [];

        for ($current = $id; $current !== null && isset($this->names[$current]); $current = $this->parents[$current]) {
            if (in_array($current, $ids, true)) {
                break;
            }

            $ids[] = $current;
        }

        return $ids;
    }

    /** 1 per una categoria principale, 2 per una sottocategoria, e cosi' via. */
    public function level(int $id): int
    {
        return count($this->lineage($id));
    }

    /** Il nome con davanti quelli delle madri: "Madre › Figlia". */
    public function path(int $id): string
    {
        return implode(' › ', array_map(fn (int $node) => $this->names[$node], array_reverse($this->lineage($id))));
    }

    /**
     * Le sottocategorie a ogni profondita', senza la categoria stessa.
     *
     * @return list<int>
     */
    public function descendants(int $id): array
    {
        $found = [];
        $queue = $this->children[$id] ?? [];

        while ($queue) {
            $child = array_shift($queue);

            if ($child === $id || in_array($child, $found, true)) {
                continue;
            }

            $found[] = $child;
            array_push($queue, ...($this->children[$child] ?? []));
        }

        return $found;
    }

    /**
     * Le figlie dirette in ordine di nome; senza argomento le categorie principali.
     *
     * Una categoria la cui madre non esiste piu' conta come principale,
     * cosi' non sparisce dal menu.
     *
     * @return array<int, string>
     */
    public function children(?int $id = null): array
    {
        $ids = $id === null
            ? array_keys(array_filter($this->parents, fn (?int $parent) => $parent === null || ! isset($this->names[$parent])))
            : array_filter($this->children[$id] ?? [], fn (int $child) => $child !== $id);

        $children = array_intersect_key($this->names, array_flip($ids));
        asort($children, SORT_NATURAL | SORT_FLAG_CASE);

        return $children;
    }

    /** Quanti livelli occupa il ramo che parte da qui: 1 se non ha sottocategorie. */
    public function height(int $id): int
    {
        $base = $this->level($id);

        return 1 + max([0, ...array_map(fn (int $node) => $this->level($node) - $base, $this->descendants($id))]);
    }

    /**
     * Tutte le categorie con il percorso completo, in ordine di percorso.
     *
     * @return array<int, string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach (array_keys($this->names) as $id) {
            $labels[$id] = $this->path($id);
        }

        asort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    /**
     * Le madri possibili per una categoria, null se e' nuova.
     *
     * Restano fuori la categoria stessa, il suo ramo e le categorie sotto
     * cui il ramo supererebbe i livelli ammessi.
     *
     * @return array<int, string>
     */
    public function parentOptions(?int $category, int $maxLevels): array
    {
        $excluded = $category !== null ? [$category, ...$this->descendants($category)] : [];
        $branch = $category !== null ? $this->height($category) : 1;

        return array_filter(
            $this->labels(),
            fn (int $id) => ! in_array($id, $excluded, true) && $this->level($id) + $branch <= $maxLevels,
            ARRAY_FILTER_USE_KEY
        );
    }

    /** Perche' $parent non puo' fare da madre a $category, o null se puo'. */
    public function parentProblem(?int $category, int $parent, int $maxLevels): ?string
    {
        if ($category !== null && ($parent === $category || in_array($parent, $this->descendants($category), true))) {
            return "Una categoria non puo' stare dentro se stessa o dentro una sua sottocategoria.";
        }

        $branch = $category !== null ? $this->height($category) : 1;

        if ($this->level($parent) + $branch > $maxLevels) {
            return "Le categorie arrivano al massimo a $maxLevels livelli.";
        }

        return null;
    }
}
