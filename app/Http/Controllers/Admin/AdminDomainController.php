<?php

namespace App\Http\Controllers\Admin;

use App\Models\CmsPage;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\ProductCategory;
use App\Support\CategoryTree;
use App\Support\Domains\DomainConnectionChecker;
use App\Support\Domains\HostName;
use App\Support\Images\ImageStore;
use App\Support\Sites\SiteContent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Domini della rete: ognuno e' un sito a se' sopra i dati del marketplace.
 *
 * Il modulo ha una vista sua, a sezioni: cosa mostra il dominio, pagina di
 * ingresso, aspetto, blocchi dello shop, menu, piede e SEO. I contenuti
 * finiscono nel campo JSON `site`, letto da SiteContent.
 */
class AdminDomainController extends AdminResourceController
{
    protected string $model = Domain::class;

    protected string $title = 'Domini';

    protected string $routePrefix = 'admin.domains';

    /** Immagini sostituite o tolte, cancellate dopo il salvataggio. */
    private array $replacedImages = [];

    protected function columns(): array
    {
        return ['name' => 'Nome', 'domain' => 'Dominio', 'type' => 'Tipo', 'connection_status' => 'Collegamento'];
    }

    protected function rowActions(): array
    {
        return [['Verifica ora', 'admin.domains.check', 'PATCH']];
    }

    /** Verifica subito DNS e certificato, senza aspettare il giro orario. */
    public function check(Domain $domain, DomainConnectionChecker $checker): RedirectResponse
    {
        $result = $checker->refresh($domain, $domain->domain);

        return $result->connected()
            ? back()->with('success', __(':dominio e collegato.', ['dominio' => $domain->domain]))
            : back()->with('error', $domain->domain.': '.$result->error);
    }

    public function create(): View
    {
        return $this->form(new Domain(['is_active' => true, 'type' => 'home', 'entry_page' => 'home', 'company_scope' => 'category']));
    }

    public function edit(Request $request): View
    {
        return $this->form($this->record($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $response = parent::store($request);
        app(ImageStore::class)->delete($this->replacedImages);

        return $response;
    }

    public function update(Request $request): RedirectResponse
    {
        $response = parent::update($request);
        app(ImageStore::class)->delete($this->replacedImages);

        return $response;
    }

    private function form(Domain $domain): View
    {
        $productTree = CategoryTree::of(ProductCategory::class);

        return view('admin.domains.form', [
            'record' => $domain,
            'title' => $this->title,
            'routePrefix' => $this->routePrefix,
            // Contenuti con i predefiniti, per mostrare nei campi vuoti cosa si vedrebbe.
            'defaults' => new SiteContent($domain->exists ? $domain : new Domain(['name' => $domain->name ?: 'Nome del sito'])),
            'types' => ['home' => 'Nessun filtro', 'category' => 'Per categoria', 'city' => 'Per città', 'category+city' => 'Per categoria e città', 'company' => 'Azienda'],
            'entryPages' => Domain::ENTRY_PAGES,
            'companyScopes' => Domain::COMPANY_SCOPES,
            'variants' => ['marketplace' => 'Marketplace blu', 'shop' => 'Shop blu', 'artisan' => 'Shop artigianale verde'],
            'companyCategories' => CategoryTree::of(CompanyCategory::class)->labels(),
            'productCategories' => $productTree->labels(),
            // Riquadri proponibili: le sottocategorie della categoria del dominio, o tutte.
            'railOptions' => $domain->product_category_id
                ? array_intersect_key($productTree->labels(), array_flip([$domain->product_category_id, ...$productTree->descendants($domain->product_category_id)]))
                : $productTree->labels(),
            'cmsPages' => CmsPage::query()->published()->orderBy('title')->pluck('title', 'id'),
            'entryCompany' => $domain->entry_company_id ? Company::find($domain->entry_company_id, ['id', 'name']) : null,
            'icons' => SiteContent::BENEFIT_ICONS,
            'sorts' => SiteContent::FEATURED_SORTS,
        ]);
    }

    /** Usati solo dall'elenco: il modulo ha la sua vista. */
    protected function fields(): array
    {
        return [];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        $color = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $text = ['nullable', 'string', 'max:255'];
        // Indirizzi interni (/prodotti), ancore (#catalogo) o siti esterni: mai javascript: e simili.
        $link = ['nullable', 'string', 'max:500', 'regex:/^(\/|#|https?:\/\/)/i'];
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', Rule::unique('domains', 'domain')->ignore($record)],
            'is_active' => ['boolean'],
            'type' => ['required', Rule::in(Domain::TYPES)],
            'company_category_id' => ['nullable', 'exists:company_categories,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'company_scope' => ['required', Rule::in(array_keys(Domain::COMPANY_SCOPES))],
            'city' => ['nullable', 'string', 'max:120'],
            'entry_page' => ['required', Rule::in(array_keys(Domain::ENTRY_PAGES))],
            'entry_company_id' => ['nullable', 'required_if:entry_page,company', 'integer', 'exists:companies,id'],
            'entry_cms_page_id' => ['nullable', 'required_if:entry_page,page', 'integer', 'exists:cms_pages,id'],

            'logo' => $image,
            'favicon' => $image,
            'remove_logo' => ['boolean'],
            'remove_favicon' => ['boolean'],
            'remove_seo_image' => ['boolean'],
            'header_variant' => ['nullable', Rule::in(['marketplace', 'shop', 'artisan'])],
            'header_background' => $color,
            'header_color' => $color,
            'header_accent' => $color,
            'header_tagline' => $text,
            'header_subline' => $text,

            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],

            'hero_image' => $image,
            'remove_hero_image' => ['boolean'],
            'seo_image' => $image,
            'site' => ['nullable', 'array'],

            'site.ads.mode' => ['nullable', Rule::in(\App\Support\Ads\AdContext::MODES)],

            'site.hero.enabled' => ['boolean'],
            'site.hero.eyebrow' => $text,
            'site.hero.title' => $text,
            'site.hero.highlight' => $text,
            'site.hero.text' => ['nullable', 'string', 'max:600'],
            'site.hero.primary_label' => ['nullable', 'string', 'max:60'],
            'site.hero.primary_url' => $link,
            'site.hero.secondary_label' => ['nullable', 'string', 'max:60'],
            'site.hero.secondary_url' => $link,
            'site.hero.badge_title' => ['nullable', 'string', 'max:30'],
            'site.hero.badge_text' => ['nullable', 'string', 'max:80'],
            'site.hero.badge_flag' => ['boolean'],
            'site.hero.script' => ['nullable', 'string', 'max:120'],

            'site.benefits.enabled' => ['boolean'],
            'site.benefits.items' => ['nullable', 'array', 'max:4'],
            'site.benefits.items.*.icon' => ['nullable', Rule::in(array_keys(SiteContent::BENEFIT_ICONS))],
            'site.benefits.items.*.title' => ['nullable', 'string', 'max:80'],
            'site.benefits.items.*.text' => ['nullable', 'string', 'max:120'],

            'site.categories.enabled' => ['boolean'],
            'site.categories.title' => $text,
            'site.categories.link_label' => ['nullable', 'string', 'max:60'],
            'site.categories.ids' => ['nullable', 'array'],
            'site.categories.ids.*' => ['integer', 'exists:product_categories,id'],
            'site.categories.offers' => ['boolean'],

            'site.featured.enabled' => ['boolean'],
            'site.featured.title' => $text,
            'site.featured.subtitle' => $text,
            'site.featured.sort' => ['nullable', Rule::in(array_keys(SiteContent::FEATURED_SORTS))],
            'site.featured.count' => ['nullable', 'integer', 'min:2', 'max:12'],
            'site.featured.search' => ['boolean'],

            'site.catalog.enabled' => ['boolean'],
            'site.catalog.title' => $text,

            'site.footer.about' => ['nullable', 'string', 'max:1000'],
            'site.footer.links_title' => ['nullable', 'string', 'max:60'],
            'site.footer.info_title' => ['nullable', 'string', 'max:60'],
            'site.footer.legal' => ['nullable', 'string', 'max:500'],
            'site.footer.copyright' => $text,

            'site.seo.title' => $text,
            'site.seo.description' => ['nullable', 'string', 'max:320'],
        ];

        foreach (['menu.left', 'menu.right', 'footer.links', 'footer.info'] as $group) {
            $rules["site.$group"] = ['nullable', 'array', 'max:'.SiteContent::LINK_ROWS];
            $rules["site.$group.*.label"] = ['nullable', 'string', 'max:60'];
            $rules["site.$group.*.url"] = $link;
        }

        return $rules;
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $images = app(ImageStore::class);
        $data['domain'] = HostName::normalize($data['domain']);
        $data['is_active'] = $request->boolean('is_active');
        $data['social_links'] = array_filter($data['social_links'] ?? []) ?: null;

        // Chi non ha scelto quella pagina di ingresso non si porta dietro l'azienda o la pagina.
        $data['entry_company_id'] = $data['entry_page'] === 'company' ? $data['entry_company_id'] : null;
        $data['entry_cms_page_id'] = $data['entry_page'] === 'page' ? $data['entry_cms_page_id'] : null;

        foreach (['logo' => 'logo', 'favicon' => 'favicon'] as $field => $profile) {
            unset($data[$field]);

            if ($request->hasFile($field)) {
                $data[$field] = $images->store($request->file($field), 'domains', $profile, $field);
                $this->replacedImages[] = $record?->$field;
            } elseif ($request->boolean("remove_$field")) {
                $data[$field] = null;
                $this->replacedImages[] = $record?->$field;
            }
        }
        unset($data['remove_logo'], $data['remove_favicon']);

        $site = $data['site'] ?? [];
        $previous = (array) ($record?->site ?? []);

        foreach (['hero_image' => 'hero.image', 'seo_image' => 'seo.image'] as $field => $key) {
            data_set($site, $key, data_get($previous, $key));

            if ($request->hasFile($field)) {
                data_set($site, $key, $images->store($request->file($field), 'domains', 'banner', $field));
                $this->replacedImages[] = data_get($previous, $key);
            } elseif ($request->boolean("remove_$field")) {
                data_set($site, $key, null);
                $this->replacedImages[] = data_get($previous, $key);
            }

            unset($data[$field], $data["remove_$field"]);
        }

        // Righe di link vuote e vantaggi senza titolo non si salvano.
        foreach (['menu.left', 'menu.right', 'footer.links', 'footer.info'] as $group) {
            data_set($site, $group, array_values(array_filter(
                (array) data_get($site, $group, []),
                fn ($row) => filled($row['label'] ?? null) && filled($row['url'] ?? null)
            )));
        }

        data_set($site, 'benefits.items', array_values(array_filter(
            (array) data_get($site, 'benefits.items', []),
            fn ($row) => filled($row['title'] ?? null)
        )));
        data_set($site, 'categories.ids', array_map('intval', (array) data_get($site, 'categories.ids', [])));

        $data['site'] = $site;
        $this->replacedImages = array_values(array_filter($this->replacedImages));

        return $data;
    }
}
