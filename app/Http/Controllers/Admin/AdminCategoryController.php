<?php

namespace App\Http\Controllers\Admin;

use App\Support\CategoryTree;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Base per le anagrafiche delle categorie, di aziende e di prodotti.
 *
 * Le categorie formano un albero: il menu della categoria superiore offre
 * ogni livello ammesso con il percorso completo, cosi' anche una categoria
 * al terzo livello ritrova la sua madre, e il salvataggio rifiuta giri
 * chiusi e rami piu' profondi del consentito.
 */
abstract class AdminCategoryController extends AdminResourceController
{
    /** Livelli ammessi, categoria principale compresa. */
    protected int $maxLevels = 2;

    private ?CategoryTree $tree = null;

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
