<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyPaymentSetting;
use App\Models\Plan;
use App\Models\ProductCategory;
use App\Models\User;
use App\Payments\KMoney\KMoneyPairing;
use App\Payments\KMoney\KMoneyPercentages;
use App\Payments\KMoney\KMoneyShare;
use App\Payments\PaymentException;
use App\Payments\Subscriptions\SubscriptionActivator;
use App\Support\BulkSelection;
use App\Support\CategoryTree;
use App\Support\Images\ImageStore;
use App\Support\Domains\DomainConnectionChecker;
use App\Support\Domains\HostName;
use App\Support\Maps\CompanyLocation;
use App\Support\RichText;
use App\Support\WorkingHours;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Aziende in amministrazione.
 *
 * La scheda ha le voci della scheda del sito originale: accesso del
 * titolare, piano, contatti, orari, galleria, logo e banner. Il piano non
 * si scrive sull'azienda: passa dagli abbonamenti, cosi' scadenze e
 * rinnovi restano coerenti con quello che si vede qui.
 */
class AdminCompanyController extends Controller
{
    /** Foto della galleria, come nel sito originale. */
    public const GALLERY_MAX = 10;

    /** Nota lasciata sugli abbonamenti aperti da qui. */
    private const PLAN_NOTE = 'Assegnato dalla scheda azienda';

    public function __construct(
        private readonly SubscriptionActivator $activator,
        private readonly KMoneyPercentages $percentages,
        private readonly CompanyLocation $location,
    ) {
    }

    public function index(Request $request): View
    {
        // Le voci del piano servono a sapere se l'azienda ha una pagina da aprire.
        $companies = $this->filters(Company::query()->with('plan:id,name,capabilities'), $request)
            // Per id e non per data: con centomila aziende risponde l'indice primario.
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.companies.index', [
            'companies' => $companies,
            'plans' => Plan::byRank()->pluck('name', 'id'),
        ]);
    }

    /** I filtri dell'elenco, gli stessi per la pagina e per "tutti i risultati". */
    public function filters(Builder $query, Request $request): Builder
    {
        $term = trim($request->string('cerca')->toString());

        return $query
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%$term%")
                ->orWhere('email', 'like', "%$term%")))
            ->when($request->integer('piano'), fn ($q, $id) => $q->where('plan_id', $id))
            ->when($request->filled('stato'), fn ($q) => $q->where('is_active', $request->input('stato') === 'attive'));
    }

    /**
     * Accende, spegne o elimina piu' aziende insieme.
     *
     * Eliminare vale solo sulle righe spuntate: un'azienda si porta via
     * prodotti, abbonamenti e pagina, e "tutti i risultati" possono essere
     * decine di migliaia.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate(
            BulkSelection::rules(['activate', 'deactivate', 'delete'], onlySelected: ['delete']),
            BulkSelection::messages()
        );

        $query = BulkSelection::query($request, Company::query(), $this->filters(...));

        $message = match ($request->input('action')) {
            'activate' => trans_choice(':count azienda accesa.|:count aziende accese.', $query->update(['is_active' => true])),
            'deactivate' => trans_choice(':count azienda spenta.|:count aziende spente.', $query->update(['is_active' => false])),
            'delete' => trans_choice(':count azienda eliminata.|:count aziende eliminate.', BulkSelection::deleteEach($query)),
        };

        return back()->with('success', $message);
    }

    public function create(): View
    {
        return view('admin.companies.form', $this->formData(new Company(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        // La ricerca dell'indirizzo va fatta fuori dalla transazione: puo' durare qualche secondo.
        $coordinates = $this->location->coordinates(new Company(), $data);

        $company = DB::transaction(function () use ($request, $data, $coordinates) {
            $owner = new User([
                'name' => $data['name'],
                'email' => $data['login_email'],
                'password' => $data['password'],
                'user_type' => 'vendor',
                'is_active' => true,
            ]);
            // Creato qui, l'indirizzo lo garantisce l'amministratore.
            $owner->forceFill(['email_verified_at' => now()])->save();

            $company = Company::create($this->attributes($data) + $coordinates + [
                'user_id' => $owner->id,
                'slug' => $this->uniqueSlug($data['name']),
            ]);

            $this->saveMedia($request, $company);
            $this->syncPlan($company, $data['plan_id'] ?? null);
            $this->saveKMoney($company, $data);

            return $company;
        });

        return redirect()->route('admin.companies.edit', $company)->with('success', __('Azienda creata.'));
    }

    public function show(Company $company): RedirectResponse
    {
        return redirect()->route('admin.companies.edit', $company);
    }

    public function edit(Company $company): View
    {
        return view('admin.companies.form', $this->formData($company->load('user')));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request, $company);
        $coordinates = $this->location->coordinates($company, $data);

        DB::transaction(function () use ($request, $company, $data, $coordinates) {
            $owner = $company->user;
            $owner->email = $data['login_email'];

            if (filled($data['password'] ?? null)) {
                $owner->password = $data['password'];
                // Chi aveva "ricordami" con la password vecchia deve rientrare.
                $owner->setRememberToken(Str::random(60));
            }

            $owner->save();

            $company->update($this->attributes($data) + $coordinates);
            $this->saveMedia($request, $company);
            $this->syncPlan($company, $data['plan_id'] ?? null);
            $this->saveKMoney($company, $data);
        });

        return back()->with('success', __('Azienda aggiornata.'));
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('admin.companies.index')->with('success', __('Azienda eliminata.'));
    }

    public function toggleStatus(Company $company): RedirectResponse
    {
        $company->update(['is_active' => ! $company->is_active]);

        return back()->with('success', __('Stato azienda aggiornato.'));
    }

    /** Verifica subito DNS e certificato del dominio proprio. */
    public function checkDomain(Company $company, DomainConnectionChecker $checker): RedirectResponse
    {
        abort_if(blank($company->custom_domain), 404);

        $result = $checker->refresh($company, $company->custom_domain);

        return $result->connected()
            ? back()->with('success', __(':dominio e collegato.', ['dominio' => $company->custom_domain]))
            : back()->with('error', $company->custom_domain.': '.$result->error);
    }

    /** Collegamento KMoney per conto dell'azienda, con il suo numero di conto. */
    public function pairKMoney(Request $request, Company $company, KMoneyPairing $pairing): RedirectResponse
    {
        $data = $request->validate(['kmoney_account_number' => ['required', 'string', 'max:40']]);

        if (! KMoneyPairing::isValidAccount(KMoneyPairing::normalize($data['kmoney_account_number']))) {
            return back()->withErrors(['kmoney_account_number' => __('Numero di conto KMoney non valido: KYB o KYP seguito da 13 caratteri.')])->withInput();
        }

        try {
            $pairing->request($company, $data['kmoney_account_number']);
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', KMoneyPairing::describe(KMoneyPairing::PENDING));
    }

    public function checkKMoney(Company $company, KMoneyPairing $pairing): RedirectResponse
    {
        try {
            $status = $pairing->check($company);
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($status === KMoneyPairing::APPROVED ? 'success' : 'error', KMoneyPairing::describe($status));
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        // Il vecchio database scriveva "-" dove il sito mancava.
        if (trim((string) $request->input('website')) === '-') {
            $request->merge(['website' => null]);
        }

        // Si salva sempre "dominio.it", comunque sia stato scritto.
        $request->merge(['custom_domain' => HostName::normalize($request->input('custom_domain'))]);

        // Anche l'amministrazione sceglie solo fra le quote che KMoney ammette per il conto.
        $kmoneySteps = KMoneyShare::steps($company?->paymentSettings()->first());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'login_email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($company?->user_id),
            ],
            'password' => [$company ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'category_id' => ['nullable', 'exists:company_categories,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'custom_domain' => [
                'nullable', 'string', 'max:255',
                Rule::unique('companies', 'custom_domain')->ignore($company?->id),
                function (string $attribute, string $value, \Closure $fail) {
                    if (! HostName::isValid($value)) {
                        $fail(__('Scrivi solo il dominio, per esempio decinabus.it.'));
                    }
                },
            ],
            'base_shipping_rate' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'per_kg_rate' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', Rule::in(config('ksm.regions'))],
            'company_location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'company_description' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['boolean'],
            'kmoney_contract_percent' => ['nullable', Rule::in($kmoneySteps)],
            'kmoney_in_debt' => ['boolean'],
            'kmoney_rules' => ['nullable', 'array'],
            'kmoney_rules.*' => ['nullable', Rule::in($kmoneySteps)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['string'],
        ] + WorkingHours::rules(), [], WorkingHours::attributes());

        $kept = array_diff((array) $company?->offer_gallery, (array) ($data['remove_gallery'] ?? []));

        if (count($kept) + count($request->file('gallery', [])) > self::GALLERY_MAX) {
            throw ValidationException::withMessages([
                'gallery' => __('La galleria tiene al massimo :max foto: togline qualcuna prima di aggiungerne.', [
                    'max' => self::GALLERY_MAX,
                ]),
            ]);
        }

        return $data;
    }

    /** I campi che vanno sull'azienda cosi' come arrivano dal modulo. */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'name', 'category_id', 'email', 'phone', 'website', 'custom_domain', 'base_shipping_rate', 'per_kg_rate',
            'address', 'city', 'region', 'company_location',
        ]) + [
            'company_description' => RichText::clean($data['company_description'] ?? null),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'working_hours' => WorkingHours::normalize($data['working_hours'] ?? null) ?: null,
        ];
    }

    /**
     * Il piano scelto nel modulo diventa un abbonamento vero.
     *
     * Scriverlo solo su `companies.plan_id` lascerebbe indietro gli
     * abbonamenti, e il giro dei rinnovi spegnerebbe l'azienda seguendo
     * il piano vecchio.
     */
    private function syncPlan(Company $company, int|string|null $planId): void
    {
        $planId = $planId ? (int) $planId : null;
        $currentId = $company->plan_id !== null ? (int) $company->plan_id : null;

        if ($planId === $currentId) {
            return;
        }

        if ($planId === null) {
            if ($current = $company->activeSubscription()) {
                $this->activator->cancel($current);
            }

            $company->update(['plan_id' => null]);

            return;
        }

        $this->activator->assign($company, Plan::findOrFail($planId), self::PLAN_NOTE);
    }

    /**
     * Logo, banner e galleria.
     *
     * Un file sostituito o tolto si cancella dal disco. Dalla galleria si
     * tolgono solo percorsi che la scheda ha davvero: il modulo non puo'
     * far cancellare file che non le appartengono.
     */
    private function saveMedia(Request $request, Company $company): void
    {
        $images = app(ImageStore::class);
        $folder = "companies/$company->id";
        $changes = [];
        $obsolete = [];

        foreach (['logo', 'banner'] as $field) {
            if ($request->hasFile($field)) {
                $changes[$field] = $images->store($request->file($field), $folder, $field, $field);
                $obsolete[] = $company->$field;
            }
        }

        $gallery = (array) $company->offer_gallery;
        $removed = array_values(array_intersect($gallery, (array) $request->input('remove_gallery', [])));

        if ($removed || $request->hasFile('gallery')) {
            $gallery = array_values(array_diff($gallery, $removed));

            foreach ($request->file('gallery', []) as $i => $file) {
                $gallery[] = $images->store($file, "$folder/galleria", 'gallery', "gallery.$i");
            }

            $changes['offer_gallery'] = $gallery ?: null;
            $obsolete = array_merge($obsolete, $removed);
        }

        if ($changes) {
            $company->update($changes);
            $images->delete($obsolete);
        }
    }

    /**
     * Quota del contratto, debito e quote per categoria di KMoney.
     *
     * Il debito lo legge kmoney:sync da GET /balance quando il venditore
     * ha collegato il conto: da li' in poi il modulo non lo cambia. La
     * quota del contratto l'API non la dice, e resta all'amministrazione.
     * Una scheda che non ha mai toccato KMoney non si porta dietro
     * impostazioni di incasso vuote.
     */
    private function saveKMoney(Company $company, array $data): void
    {
        $contract = isset($data['kmoney_contract_percent']) ? (int) $data['kmoney_contract_percent'] : null;
        $rules = (array) ($data['kmoney_rules'] ?? []);
        $settings = $company->paymentSettings()->first();
        $inDebt = $settings?->kmoney_synced_at
            ? (bool) $settings->kmoney_in_debt
            : (bool) ($data['kmoney_in_debt'] ?? false);

        $chosen = array_filter($rules, fn ($percent) => $percent !== null && $percent !== '');

        if (! $settings && $contract === null && ! $inDebt && ! $chosen) {
            return;
        }

        $settings ??= CompanyPaymentSetting::create(['company_id' => $company->id]);
        $settings->forceFill(['kmoney_contract_percent' => $contract, 'kmoney_in_debt' => $inDebt])->save();

        $this->percentages->saveCategoryRules($company, $rules);
    }

    /** Lo slug e' l'indirizzo pubblico: nasce una volta e non segue i cambi di nome. */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'azienda';
        $slug = $base;

        for ($i = 2; Company::where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    private function formData(Company $company): array
    {
        return [
            'company' => $company,
            'plans' => Plan::byRank()->get(),
            // Con il percorso: al terzo livello i nomi da soli non dicono dove stanno.
            'categories' => CategoryTree::of(CompanyCategory::class)->labels(),
            'days' => WorkingHours::DAYS,
            'galleryMax' => self::GALLERY_MAX,
            'subscription' => $company->exists ? $company->activeSubscription() : null,
            // Il periodo scelto e non ancora incassato: senza, il modulo
            // direbbe "nessun piano" a un'azienda che sta aspettando.
            'pending' => $company->exists ? $company->pendingSubscription() : null,
            'kmoney' => $company->exists ? $company->paymentSettings()->first() : null,
            'kmoneyRules' => $company->exists ? KMoneyPercentages::rulesFor($company->id) : [],
            'kmoneySteps' => KMoneyShare::steps($company->exists ? $company->paymentSettings : null),
            // Solo le categorie in cui l'azienda ha prodotti.
            'productCategories' => $company->exists
                ? ProductCategory::whereKey(
                    $company->products()->whereNotNull('category_id')->distinct()->pluck('category_id')
                )->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}
