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

    /** Regole comuni: azione, portata e caselle. */
    public static function rules(array $actions = self::ACTIONS): array
    {
        return [
            'action' => ['required', 'in:'.implode(',', $actions)],
            'scope' => ['nullable', 'in:selected,all'],
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
}
