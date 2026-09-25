<?php

namespace App\Http\Controllers\Admin;

use App\Support\CategoryTree;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Base per le anagrafiche delle categorie, di aziende e di prodotti.
 *
 * Le categorie formano un albero: il menu della categoria superiore offre
 * ogni livello ammesso con il percorso completo, cosi' anche una categoria
 * al terzo livello ritrova la sua madre, e il salvataggio rifiuta giri
 * chiusi e rami piu' profondi del consentito.
 *
 * L'elenco e' l'albero intero, non una pagina di righe: si aprono i rami,
 * si aggiunge una sottocategoria dalla riga della madre e si vede quante
 * aziende o quanti prodotti stanno in ogni ramo.
 */
abstract class AdminCategoryController extends AdminResourceController
{
    /** Livelli ammessi, categoria principale compresa. */
    protected int $maxLevels = 2;

    /** Relazione con cio' che sta nella categoria, per contarlo: companies o products. */
    protected string $itemsRelation;

    /** Come si chiamano, al plurale e al singolare. */
    protected string $itemsLabel;

    protected string $itemLabel;

    private ?CategoryTree $tree = null;

    public function index(Request $request): View
    {
        $model = $this->model;
        $rows = $model::query()->withCount($this->itemsRelation)->get()->keyBy('id');
        $tree = $this->tree();

        // Con una ricerca restano le categorie trovate e le loro madri, per non perdere il ramo.
        $term = Str::lower(trim($request->string('cerca')->toString()));
        $visible = null;

        if ($term !== '') {
            $visible = [];

            foreach ($rows as $row) {
                if (Str::contains(Str::lower($row->name.' '.$row->slug), $term)) {
                    array_push($visible, ...$tree->lineage($row->id));
                }
            }

            $visible = array_flip($visible);
        }

        $build = function (?int $parent) use (&$build, $rows, $tree, $visible): array {
            $nodes = [];

            foreach (array_keys($tree->children($parent)) as $id) {
                if ($visible !== null && ! isset($visible[$id])) {
                    continue;
                }

                $row = $rows[$id];
                $children = $build($id);

                $nodes[] = [
                    'record' => $row,
                    'level' => $tree->level($id),
                    'parent' => $parent !== null ? $rows[$parent]->name : null,
                    'count' => (int) $row->{$this->itemsRelation.'_count'},
                    'total' => (int) $row->{$this->itemsRelation.'_count'} + array_sum(array_map(
                        fn (int $child) => (int) $rows[$child]->{$this->itemsRelation.'_count'},
                        $tree->descendants($id)
                    )),
                    'children' => $children,
                ];
            }

            return $nodes;
        };

        $roots = count($tree->children());

        return view('admin.categories.index', [
            'title' => $this->title,
            'routePrefix' => $this->routePrefix,
            'nodes' => $build(null),
            'search' => $term,
            'maxLevels' => $this->maxLevels,
            'parentOptions' => $tree->parentOptions(null, $this->maxLevels),
            'itemsLabel' => $this->itemsLabel,
            'itemLabel' => $this->itemLabel,
            'stats' => [
                'total' => $rows->count(),
                'roots' => $roots,
                'children' => $rows->count() - $roots,
                'empty' => $rows->filter(fn ($row) => ! $row->{$this->itemsRelation.'_count'})->count(),
            ],
        ]);
    }

    /** "Nuova sottocategoria" arriva con la madre gia' scelta. */
    public function create(): View
    {
        $record = new $this->model;
        $parent = request()->integer('madre');

        if ($parent && $this->tree()->parentProblem(null, $parent, $this->maxLevels) === null) {
            $record->parent_id = $parent;
        }

        return view('admin.resource.form', ['record' => $record] + $this->shared());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->transform($request->validate($this->rules($request)), $request);
        $record = $this->model::create($data);

        return redirect()->to(route("$this->routePrefix.index").'#categoria-'.$record->getKey())
            ->with('success', __('Categoria «:name» creata.', ['name' => $record->name]));
    }

    /** Niente eliminazione in blocco: quella singola sposta prima il ramo, questa no. */
    public function bulk(Request $request): RedirectResponse
    {
        abort(404);
    }

    /**
     * Elimina la categoria senza portarsi dietro il ramo.
     *
     * Nel database le sottocategorie cadrebbero insieme alla madre: qui
     * salgono prima di un livello, e aziende o prodotti passano alla
     * categoria superiore invece di restare senza.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $record = $this->record($request);
        $parent = $record->parent_id;

        DB::transaction(function () use ($record, $parent) {
            $this->model::query()->where('parent_id', $record->getKey())->update(['parent_id' => $parent]);
            $record->{$this->itemsRelation}()->update(['category_id' => $parent]);
            $record->delete();
        });

        CategoryTree::forget($this->model);

        return redirect()->route("$this->routePrefix.index")
            ->with('success', __('Categoria «:name» eliminata.', ['name' => $record->name]));
    }

    protected function columns(): array
    {
        return [
            'name' => 'Nome',
            'parent_path' => 'Categoria superiore',
            'level' => 'Livello',
        ];
    }

    protected function prepareRows(Collection $records): void
    {
        foreach ($records as $record) {
            $record->setAttribute('parent_path', $record->parent_id ? $this->tree()->path($record->parent_id) : '—');
            $record->setAttribute('level', $this->tree()->level($record->id));
        }
    }

    protected function formData(): array
    {
        $editing = $this->editing ? (int) $this->editing->getKey() : null;

        return ['parent_id' => $this->tree()->parentOptions($editing, $this->maxLevels)];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nome', 'type' => 'text'],
            'slug' => ['label' => 'Slug', 'type' => 'text'],
            'parent_id' => [
                'label' => 'Categoria superiore',
                'type' => 'select',
                'empty' => 'Nessuna',
                'hint' => "Le categorie arrivano al massimo a {$this->maxLevels} livelli.",
            ],
            'description' => ['label' => 'Descrizione', 'type' => 'textarea'],
        ];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        $table = (new $this->model)->getTable();
        $category = $record ? (int) $record->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique($table, 'slug')->ignore($record)],
            'parent_id' => [
                'nullable',
                "exists:$table,id",
                function (string $attribute, mixed $value, Closure $fail) use ($category) {
                    $problem = $this->tree()->parentProblem($category, (int) $value, $this->maxLevels);

                    if ($problem !== null) {
                        $fail($problem);
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        return $data;
    }

    private function tree(): CategoryTree
    {
        return $this->tree ??= CategoryTree::of($this->model);
    }
}
