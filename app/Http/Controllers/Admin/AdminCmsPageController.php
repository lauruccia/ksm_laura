<?php

namespace App\Http\Controllers\Admin;

use App\Models\CmsPage;
use App\Models\Domain;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            // La stessa pagina puo' stare su piu' siti: si vede solo su quelli spuntati.
            'sites' => collect([CmsPage::PLATFORM => 'Sito principale (KSM)'])
                ->union(Domain::query()->orderBy('domain')->pluck('domain', 'id')),
            'status' => collect(['draft' => 'Bozza', 'published' => 'Pubblicata']),
            'visibility' => collect(['visible' => 'Visibile', 'hidden' => 'Nascosta']),
            'locations' => collect(['header' => 'Intestazione', 'footer' => 'Piede']),
        ];
    }

    protected function fields(): array
    {
        return [
            'sites' => ['label' => 'Siti', 'type' => 'multiselect', 'placeholder' => 'Cerca un sito'],
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
            'sites' => ['required', 'array', 'min:1'],
            'sites.*' => ['string', Rule::in([CmsPage::PLATFORM, ...Domain::query()->pluck('id')->map(fn ($id) => (string) $id)->all()])],
            // Unico dentro ciascun sito: lo controlla transform(), quando lo slug e' deciso.
            'slug' => ['nullable', 'string', 'max:255'],
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
        $records->load('domains');
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $this->ensureSlugIsFree($data['slug'], $data['sites'], $record);
        $data['include_in_sitemap'] = $request->boolean('include_in_sitemap');
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        Navigation::flush();

        return $data;
    }

    /**
     * Su ogni sito un indirizzo porta a una pagina sola: lo slug non deve
     * essere gia' di un'altra pagina che sta su uno dei siti scelti.
     *
     * @param  list<string>  $sites
     */
    private function ensureSlugIsFree(string $slug, array $sites, ?Model $record): void
    {
        $domains = array_map('intval', array_diff($sites, [CmsPage::PLATFORM]));

        $taken = CmsPage::query()
            ->where('slug', $slug)
            ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
            ->where(fn ($q) => $q
                ->when(in_array(CmsPage::PLATFORM, $sites, true), fn ($q) => $q->orWhere('on_platform', true))
                ->when($domains, fn ($q) => $q->orWhereHas('domains', fn ($d) => $d->whereKey($domains))))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'slug' => __('Su uno dei siti scelti c\'e\' gia\' una pagina con questo indirizzo.'),
            ]);
        }
    }
}
