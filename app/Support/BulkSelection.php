<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Le righe scelte in un elenco per un'azione in blocco.
 *
 * O le caselle spuntate (`ids[]`), o tutti i risultati della ricerca in
 * corso su ogni pagina (`scope=all`, con gli stessi filtri dell'elenco).
 * In entrambi i casi si parte dalla query base di chi agisce, cosi' un
 * venditore non tocca mai righe di altri anche mandando id a mano.
 */
final class BulkSelection
{
    public const ACTIONS = ['activate', 'deactivate', 'kmoney', 'delete'];

    /**
     * Regole comuni: azione, portata e caselle.
     *
     * @param  list<string>  $onlySelected  azioni che non valgono su tutti i risultati
     */
    public static function rules(array $actions = self::ACTIONS, array $onlySelected = []): array
    {
        return [
            'action' => ['required', 'in:'.implode(',', $actions)],
            'scope' => ['nullable', 'in:selected,all', function (string $attribute, mixed $value, \Closure $fail) use ($onlySelected) {
                if ($value === 'all' && in_array(request()->input('action'), $onlySelected, true)) {
                    $fail(__('Questa azione vale solo sulle righe scelte a mano.'));
                }
            }],
            'ids' => ['required_unless:scope,all', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    public static function messages(): array
    {
        return ['ids.required_unless' => __('Seleziona almeno una riga.')];
    }

    /**
     * @param  Builder  $base  le righe su cui chi agisce ha diritto
     * @param  callable(Builder, Request): Builder  $filters  i filtri dell'elenco
     */
    public static function query(Request $request, Builder $base, callable $filters): Builder
    {
        return $request->input('scope') === 'all'
            ? $filters($base, $request)
            : $base->whereKey($request->input('ids', []));
    }

    /**
     * Elimina una riga alla volta, cosi' partono gli eventi del modello
     * come nell'eliminazione singola. Ritorna quante ne ha eliminate.
     *
     * @param  (callable(\Illuminate\Database\Eloquent\Model): bool)|null  $allowed  per saltare le righe da tenere
     */
    public static function deleteEach(Builder $query, ?callable $allowed = null): int
    {
        $count = 0;

        $query->chunkById(200, function ($records) use (&$count, $allowed) {
            foreach ($records as $record) {
                if ($allowed === null || $allowed($record)) {
                    $record->delete();
                    $count++;
                }
            }
        });

        return $count;
    }
}
