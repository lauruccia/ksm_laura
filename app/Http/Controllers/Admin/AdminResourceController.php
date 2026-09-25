<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BulkSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Base per le anagrafiche di amministrazione.
 *
 * Le sottoclassi dichiarano modello, titolo, campi e regole di validazione.
 * Elenco e modulo sono due viste generiche condivise, cosi' una nuova
 * anagrafica non porta con se' altre pagine da mantenere.
 */
abstract class AdminResourceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** Titolo mostrato in cima alla pagina. */
    protected string $title = '';

    /** Prefisso dei nomi di rotta, per esempio "admin.plans". */
    protected string $routePrefix;

    protected string $searchColumn = 'name';

    /** Il record aperto nel modulo di modifica, per le scelte che dipendono da lui. */
    protected ?Model $editing = null;

    /**
     * Campi del modulo, nella forma chiave => [label, type, ...].
     * Tipi riconosciuti: text, email, url, number, textarea, select, checkbox, file, list.
     */
    abstract protected function fields(): array;

    abstract protected function rules(Request $request, ?Model $record = null): array;

    /** Colonne mostrate in elenco, nella forma attributo => intestazione. */
    protected function columns(): array
    {
        return [$this->searchColumn => __('Nome')];
    }

    /** Dati aggiuntivi per i campi di tipo select. */
    protected function formData(): array
    {
        return [];
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        return $data;
    }

    /** Aggiunge alle righe della pagina valori calcolati da mostrare in elenco. */
    protected function prepareRows(Collection $records): void
    {
    }

    /**
     * Pulsanti in piu' su ogni riga dell'elenco, oltre a Modifica ed Elimina.
     *
     * @return list<array{0: string, 1: string, 2: string}> etichetta, nome di rotta, metodo HTTP
     */
    protected function rowActions(): array
    {
        return [];
    }

    public function index(Request $request): View
    {
        $records = $this->filters($this->model::query(), $request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $this->prepareRows($records->getCollection());

        return view('admin.resource.index', ['records' => $records] + $this->shared());
    }

    /** I filtri dell'elenco, gli stessi per la pagina e per "tutti i risultati". */
    public function filters(Builder $query, Request $request): Builder
    {
        return $query->when(
            $request->string('cerca')->toString(),
            fn ($q, $term) => $q->where($this->searchColumn, 'like', "%$term%")
        );
    }

    /**
     * Elimina piu' elementi insieme, solo fra quelli spuntati.
     *
     * Vale per le anagrafiche che registrano la rotta `<prefisso>.bulk`:
     * le categorie no, perche' la loro eliminazione sposta prima il ramo.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate(BulkSelection::rules(['delete'], onlySelected: ['delete']), BulkSelection::messages());

        $count = BulkSelection::deleteEach(BulkSelection::query($request, $this->model::query(), $this->filters(...)));

        return back()->with('success', trans_choice(':count elemento eliminato.|:count elementi eliminati.', $count));
    }

    public function create(): View
    {
        return view('admin.resource.form', ['record' => new $this->model] + $this->shared());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->transform($request->validate($this->rules($request)), $request);

        $this->model::create($data);

        return redirect()->route("$this->routePrefix.index")->with('success', __('Elemento creato.'));
    }

    public function show(Request $request): RedirectResponse
    {
        return redirect()->route("$this->routePrefix.edit", $this->record($request));
    }

    public function edit(Request $request): View
    {
        $this->editing = $this->record($request);

        return view('admin.resource.form', ['record' => $this->editing] + $this->shared());
    }

    public function update(Request $request): RedirectResponse
    {
        $record = $this->record($request);

        $data = $this->transform($request->validate($this->rules($request, $record)), $request, $record);

        $record->update($data);

        return back()->with('success', __('Modifiche salvate.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->record($request)->delete();

        return redirect()->route("$this->routePrefix.index")->with('success', __('Elemento eliminato.'));
    }

    /**
     * Il record indicato dall'indirizzo.
     *
     * Non si puo' dichiarare come argomento: ogni anagrafica chiama il
     * suo parametro di rotta a modo suo, e `Model` e' astratto, quindi
     * il contenitore non saprebbe cosa costruire. Qui il valore si legge
     * dalla rotta e il modello lo dice la sottoclasse.
     */
    protected function record(Request $request): Model
    {
        $key = collect($request->route()->parameters())->first();

        abort_unless($key !== null, 404);

        return $this->model::query()->findOrFail($key);
    }

    protected function shared(): array
    {
        return [
            'title' => $this->title,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->fields(),
            'columns' => $this->columns(),
            'options' => $this->formData(),
            'rowActions' => $this->rowActions(),
        ];
    }
}
