<?php

namespace App\Http\Controllers\Admin;

use App\Models\CmsPage;
use App\Models\Domain;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCmsPageController extends AdminResourceController
{
    protected string $model = CmsPage::class;

    protected string $title = 'Pagine';

    protected string $routePrefix = 'admin.cms';

    protected string $searchColumn = 'title';

    protected function columns(): array
    {
        return ['title' => 'Titolo', 'slug' => 'Slug', 'site_name' => 'Sito', 'status' => 'Stato'];
    }

    protected function formData(): array
    {
        return [
            // Vuoto: il sito principale. Una pagina si vede solo sul suo sito.
            'domain_id' => Domain::query()->orderBy('domain')->pluck('domain', 'id'),
            'status' => collect(['draft' => 'Bozza', 'published' => 'Pubblicata']),
            'visibility' => collect(['visible' => 'Visibile', 'hidden' => 'Nascosta']),
            'locations' => collect(['header' => 'Intestazione', 'footer' => 'Piede']),
        ];
    }

    protected function fields(): array
    {
        return [
            'domain_id' => ['label' => 'Sito', 'type' => 'select', 'empty' => 'Sito principale (KSM)'],
            'title' => ['label' => 'Titolo', 'type' => 'text'],
            'slug' => ['label' => 'Slug', 'type' => 'text'],
            'content' => ['label' => 'Contenuto', 'type' => 'textarea', 'rows' => 14],
            'status' => ['label' => 'Stato', 'type' => 'select'],
            'visibility' => ['label' => 'Visibilita', 'type' => 'select'],
            'locations' => ['label' => 'Mostra in', 'type' => 'checkboxes'],
            'sort_order' => ['label' => 'Ordine', 'type' => 'number'],
            'meta_title' => ['label' => 'Titolo per i motori', 'type' => 'text'],
            'meta_description' => ['label' => 'Descrizione per i motori', 'type' => 'textarea'],
            'include_in_sitemap' => ['label' => 'Includi nella sitemap', 'type' => 'checkbox'],
        ];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'domain_id' => ['nullable', 'integer', 'exists:domains,id'],
            // Lo slug e' unico dentro il sito: due domini possono avere la stessa "chi-siamo".
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cms_pages', 'slug')->ignore($record)
                ->where(fn ($q) => $request->filled('domain_id') ? $q->where('domain_id', $request->integer('domain_id')) : $q->whereNull('domain_id'))],
            'content' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
            'visibility' => ['required', 'in:visible,hidden'],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['string', 'in:header,footer'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'include_in_sitemap' => ['boolean'],
        ];
    }

    protected function prepareRows(\Illuminate\Support\Collection $records): void
    {
        $records->load('domain');
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['domain_id'] = $data['domain_id'] ?? null;
        $data['include_in_sitemap'] = $request->boolean('include_in_sitemap');
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        Navigation::flush();

        return $data;
    }
}
