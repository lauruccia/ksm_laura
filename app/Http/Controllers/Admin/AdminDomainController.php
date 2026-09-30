<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\RegisterDomainOnHostingPanel;
use App\Models\CmsPage;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\HostingSetting;
use App\Models\ProductCategory;
use App\Support\Ads\AdContext;
use App\Support\BulkSelection;
use App\Support\CategoryTree;
use App\Support\Domains\CpanelHostingPanel;
use App\Support\Domains\DomainConnectionChecker;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\HostName;
use App\Support\Domains\NoHostingPanel;
use App\Support\Domains\WhmHostingPanel;
use App\Support\Images\ImageStore;
use App\Support\Sites\SiteContent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

    /** Stati del collegamento per i filtri, nell'ordine in cui si risolvono. */
    public const STATES = [
        'errore' => 'Con errore',
        'da_verificare' => 'Da verificare',
        'dns' => 'DNS da configurare',
        'certificato' => 'Certificato in attesa',
        'collegato' => 'Collegato',
    ];

    public const SORTS = [
        'recenti' => 'Più recenti',
        'nome' => 'Nome (A-Z)',
        'dominio' => 'Dominio (A-Z)',
        'stato' => 'Stato (da sistemare prima)',
        'verifica' => 'Verificati da più tempo',
    ];

    public const PER_PAGE = [20, 50, 100, 200];

    /**
     * L'elenco ha una vista sua: con centinaia di domini servono filtri per
     * stato, tipo e attivo, l'ordinamento, e i contatori per stato in cima.
     */
    public function index(Request $request): View
    {
        $perPage = in_array($request->integer('per_pagina'), self::PER_PAGE, true) ? $request->integer('per_pagina') : 50;

        $records = $this->sorted($this->filters(Domain::query(), $request), $request->string('ordina')->toString())
            ->paginate($perPage)
            ->withQueryString();

        // I contatori seguono ricerca, tipo e attivo, non lo stato scelto: dicono quanti ce ne sono per ogni stato.
        $counts = [];
        foreach (array_keys(self::STATES) as $state) {
            $counts[$state] = $this->filters(Domain::query(), $request->duplicate(array_merge($request->query(), ['stato' => $state])))->count();
        }

        return view('admin.domains.index', [
            'records' => $records,
            'counts' => $counts,
            'states' => self::STATES,
            'sorts' => self::SORTS,
            'perPageOptions' => self::PER_PAGE,
            'types' => Domain::TYPES,
            'hosting' => $this->hostingSummary(),
        ] + $this->shared());
    }

    public function filters(Builder $query, Request $request): Builder
    {
        $term = trim($request->string('cerca')->toString());

        return $query
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$term%")->orWhere('domain', 'like', "%$term%")))
            ->when(in_array($request->input('tipo'), Domain::TYPES, true), fn ($q) => $q->where('type', $request->input('tipo')))
            ->when(in_array($request->input('attivo'), ['1', '0'], true), fn ($q) => $q->where('is_active', $request->input('attivo') === '1'))
            ->when($request->input('stato'), fn ($q, $state) => match ($state) {
                'collegato' => $q->whereNotNull('dns_verified_at')->whereNotNull('ssl_verified_at'),
                'certificato' => $q->whereNotNull('dns_verified_at')->whereNull('ssl_verified_at'),
                'dns' => $q->whereNotNull('domain_checked_at')->whereNull('dns_verified_at'),
                'da_verificare' => $q->whereNull('domain_checked_at'),
                'errore' => $q->whereNotNull('domain_error')->where('domain_error', '!=', ''),
                default => $q,
            });
    }

    private function sorted(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'nome' => $query->orderBy('name')->orderBy('domain'),
            'dominio' => $query->orderBy('domain'),
            // Prima quelli da sistemare: mai verificati, DNS, certificato, poi i collegati.
            'stato' => $query->orderByRaw('CASE WHEN domain_checked_at IS NULL THEN 0 WHEN dns_verified_at IS NULL THEN 1 WHEN ssl_verified_at IS NULL THEN 2 ELSE 3 END')->orderBy('domain'),
            'verifica' => $query->orderByRaw('domain_checked_at IS NULL DESC')->orderBy('domain_checked_at')->orderBy('domain'),
            default => $query->latest()->orderByDesc('id'),
        };
    }

    /**
     * Azioni in blocco: verifica (solo DNS e certificato), ricollega (di nuovo
     * sul pannello dell'hosting, poi verifica), attiva, disattiva, elimina.
     * Verifica e ricollega vanno in coda: con centinaia di domini una pagina
     * sola non basterebbe, e la WHM fa ripartire Apache.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $actions = ['verify', 'reconnect', 'activate', 'deactivate', 'delete'];
        $request->validate(BulkSelection::rules($actions, onlySelected: ['delete']), BulkSelection::messages());

        $query = BulkSelection::query($request, Domain::query(), $this->filters(...));
        $action = $request->input('action');

        if ($action === 'delete') {
            $count = BulkSelection::deleteEach($query);

            return back()->with('success', trans_choice(':count dominio eliminato.|:count domini eliminati.', $count));
        }

        if (in_array($action, ['activate', 'deactivate'], true)) {
            $count = 0;
            $query->chunkById(200, function ($domains) use ($action, &$count) {
                foreach ($domains as $domain) {
                    $domain->update(['is_active' => $action === 'activate']);
                    $count++;
                }
            });

            return back()->with('success', trans_choice(':count dominio aggiornato.|:count domini aggiornati.', $count));
        }

        $count = 0;
        $register = $action === 'reconnect';
        $query->chunkById(200, function ($domains) use ($register, &$count) {
            foreach ($domains as $domain) {
                RegisterDomainOnHostingPanel::dispatch(Domain::class, $domain->getKey(), $domain->domain, $register);
                $count++;
            }
        });

        return back()->with('success', trans_choice(
            $register
                ? ':count dominio messo in coda per il ricollegamento: lo stato si aggiorna nei prossimi minuti.|:count domini messi in coda per il ricollegamento: lo stato si aggiorna nei prossimi minuti.'
                : ':count dominio messo in coda per la verifica: lo stato si aggiorna nei prossimi minuti.|:count domini messi in coda per la verifica: lo stato si aggiorna nei prossimi minuti.',
            $count
        ));
    }

    /** Server e pannello in uso, per la riga in cima all'elenco e la pagina Server e hosting. */
    private function hostingSummary(): array
    {
        $panel = app(HostingPanel::class);

        return [
            'panel' => match (true) {
                $panel instanceof WhmHostingPanel => 'WHM',
                $panel instanceof CpanelHostingPanel => 'cPanel',
                default => 'Nessun pannello',
            },
            'ips' => (array) config('ksm.server.ips'),
            'cname' => config('ksm.server.cname'),
            'source' => rescue(fn () => HostingSetting::query()->value('panel'), null, false) ?: 'env',
        ];
    }

    public function hosting(): View
    {
        return view('admin.domains.hosting', [
            'setting' => HostingSetting::current(),
            'panels' => HostingSetting::PANELS,
            'hosting' => $this->hostingSummary(),
            'env' => [
                'ips' => implode(', ', (array) config('ksm.server.ips')),
                'whm' => array_filter(['url' => config('ksm.whm.url'), 'account' => config('ksm.whm.account'), 'proxy_plan' => config('ksm.whm.proxy_plan')]),
            ],
            'domainCount' => Domain::count(),
        ]);
    }

    public function updateHosting(Request $request): RedirectResponse
    {
        $setting = HostingSetting::current();
        $panel = $request->input('panel');

        $data = $request->validate([
            'panel' => ['required', Rule::in(array_keys(HostingSetting::PANELS))],
            'server_ips' => ['nullable', 'required_unless:panel,env', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $ip) {
                    if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                        $fail("{$ip} non è un indirizzo IP.");
                    }
                }
            }],
            'server_cname' => ['nullable', 'string', 'max:255'],
            'cpanel_url' => ['nullable', 'required_if:panel,cpanel', 'url', 'max:255'],
            'cpanel_user' => ['nullable', 'required_if:panel,cpanel', 'string', 'max:64'],
            'cpanel_token' => ['nullable', Rule::requiredIf($panel === 'cpanel' && blank($setting->cpanel_token)), 'string', 'max:255'],
            'cpanel_docroot' => ['nullable', 'string', 'max:255'],
            'whm_url' => ['nullable', 'required_if:panel,whm', 'url', 'max:255'],
            'whm_reseller' => ['nullable', 'required_if:panel,whm', 'string', 'max:64'],
            'whm_token' => ['nullable', Rule::requiredIf($panel === 'whm' && blank($setting->whm_token)), 'string', 'max:255'],
            'whm_account' => ['nullable', 'required_if:panel,whm', 'string', 'max:64'],
            'whm_proxy_plan' => ['nullable', 'string', 'max:128'],
            'whm_proxy_target' => ['nullable', 'url', 'max:255'],
            'whm_contact_email' => ['nullable', 'email', 'max:255'],
        ], [
            'server_ips.required_unless' => 'Scrivi l\'IP del server: i domini dei clienti devono puntare lì.',
        ]);

        // Un token vuoto nel modulo vuol dire "lascia quello salvato".
        foreach (['cpanel_token', 'whm_token'] as $token) {
            if (blank($data[$token] ?? null)) {
                unset($data[$token]);
            }
        }

        $setting->update($data);
        app()->forgetInstance(HostingPanel::class);

        return redirect()->route('admin.domains.hosting')->with('success', __('Impostazioni salvate. Prova la connessione, poi ricollega i domini se hai cambiato server.'));
    }

    /** Prova la connessione con il pannello scelto, senza cambiare niente. */
    public function testHosting(): RedirectResponse
    {
        $panel = app(HostingPanel::class);

        if (! method_exists($panel, 'probe')) {
            return back()->with('success', __('Nessun pannello da provare: i domini vanno aggiunti al server web a mano (o ci pensa Caddy sul VPS).'));
        }

        $error = $panel->probe();

        return $error ? back()->with('error', $error) : back()->with('success', __('Connessione riuscita.'));
    }

    /** Verifica subito DNS e certificato, senza aspettare il giro orario. */
    public function check(Domain $domain, DomainConnectionChecker $checker): RedirectResponse
    {
        // Il pannello dell'hosting si aggiorna dalla coda: qui solo DNS e certificato.
        $panel = ! app(HostingPanel::class) instanceof NoHostingPanel;
        $result = $checker->refresh($domain, $domain->domain, register: ! $panel);

        if ($panel) {
            RegisterDomainOnHostingPanel::dispatch(Domain::class, $domain->getKey(), $domain->domain);
        }

        return $result->connected()
            ? back()->with('success', __(':dominio e collegato.', ['dominio' => $domain->domain]))
            : back()->with('error', $domain->domain.': '.$result->error
                .($panel ? ' '.__("Il pannello dell'hosting si aggiorna entro un paio di minuti: poi riverifica.") : ''));
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
            // Solo le pagine del dominio: quelle di KSM non si aprono qui.
            'cmsPages' => $domain->exists
                ? CmsPage::query()->whereHas('domains', fn ($q) => $q->whereKey($domain->getKey()))->published()->orderBy('title')->pluck('title', 'id')
                : collect(),
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
            // Una pagina del dominio. Per un dominio nuovo non ce ne sono ancora: prima si crea il dominio.
            'entry_cms_page_id' => ['nullable', 'required_if:entry_page,page', 'integer',
                Rule::exists('cms_page_domain', 'cms_page_id')->where('domain_id', $record?->getKey() ?? 0)],

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

            'site.ads.mode' => ['nullable', Rule::in(AdContext::MODES)],

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
            'site.benefits.shop' => ['boolean'],
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
