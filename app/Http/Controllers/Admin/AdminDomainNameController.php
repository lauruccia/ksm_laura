<?php

namespace App\Http\Controllers\Admin;

use App\Models\DomainName;
use App\Support\DomainNameList;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Nomi a dominio del portafoglio KSM: elenco, modifica e aggiunta in blocco. */
class AdminDomainNameController extends AdminResourceController
{
    protected string $model = DomainName::class;

    protected string $title = 'Nomi a dominio';

    protected string $routePrefix = 'admin.domain_names';

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nome a dominio (senza www)', 'type' => 'text'],
            'category' => ['label' => 'Tema', 'type' => 'select', 'empty' => 'Scelto in automatico'],
            'is_active' => ['label' => 'Visibile nella pagina pubblica', 'type' => 'checkbox'],
        ];
    }

    protected function formData(): array
    {
        return ['category' => collect(DomainNameList::themes())->mapWithKeys(fn ($theme) => [$theme => $theme])->all()];
    }

    protected function headerLinks(): array
    {
        return ['Aggiungi più nomi' => route('admin.domain_names.import')];
    }

    protected function columns(): array
    {
        return ['name' => __('Nome'), 'extension' => __('Estensione'), 'category' => __('Tema'), 'is_active' => __('Visibile')];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        // Il controllo dei doppioni deve vedere il nome ripulito, non "WWW.Pub.it ".
        if ($request->has('name')) {
            $request->merge(['name' => DomainNameList::normalize((string) $request->input('name')) ?? $request->input('name')]);
        }

        return [
            'name' => ['required', 'string', 'max:190', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/',
                Rule::unique('domain_names', 'name')->ignore($record)],
            'category' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ];
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data['category'] = $data['category'] ?? null ?: DomainNameList::theme($data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    public function filters(Builder $query, Request $request): Builder
    {
        return $query->when(
            $request->string('cerca')->toString(),
            fn ($q, $term) => $q->matching($term)
        );
    }

    public function index(Request $request): View
    {
        $records = $this->filters($this->model::query(), $request)
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.resource.index', ['records' => $records] + $this->shared());
    }

    /** Il modulo per incollare tanti nomi insieme. */
    public function import(): View
    {
        return view('admin.domain-names.import', ['themes' => DomainNameList::themes()]);
    }

    public function storeImport(Request $request): RedirectResponse
    {
        $request->validate([
            'elenco' => ['nullable', 'string', 'max:500000'],
            'file' => ['nullable', 'file', 'mimes:txt,csv', 'max:2048'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $text = (string) $request->input('elenco');
        if ($request->hasFile('file')) {
            $text .= "\n".file_get_contents($request->file('file')->getRealPath());
        }

        $parsed = DomainNameList::parse($text);

        if ($parsed['valid'] === [] && $parsed['invalid'] === []) {
            return back()->withInput()->withErrors(['elenco' => 'Incolla almeno un nome a dominio, uno per riga.']);
        }

        $existing = DomainName::query()->whereIn('name', array_keys($parsed['valid']))->pluck('name')->all();
        $new = array_diff_key($parsed['valid'], array_flip($existing));
        $forced = trim((string) $request->input('category'));
        $now = now();

        foreach (array_chunk($new, 200, true) as $chunk) {
            DomainName::query()->insertOrIgnore(array_map(fn (string $host) => [
                'name' => $host,
                'extension' => DomainNameList::extension($host),
                'category' => $forced !== '' ? $forced : DomainNameList::theme($host),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($chunk)));
        }

        $message = trans_choice(':count nome aggiunto|:count nomi aggiunti', count($new)).'; '
            .trans_choice(':count già presente|:count già presenti', count($existing)).'.';

        if ($parsed['invalid']) {
            $message .= ' Non validi, saltati: '.implode(', ', array_slice($parsed['invalid'], 0, 15))
                .(count($parsed['invalid']) > 15 ? '…' : '').'.';
        }

        return redirect()->route('admin.domain_names.index')->with('success', $message);
    }
}
