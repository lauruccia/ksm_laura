<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\Advertiser;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Support\Ads\AdContext;
use App\Support\Ads\Placements;
use App\Support\Images\ImageStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Campagne del circuito banner.
 *
 * L'amministrazione le crea e le fattura a parte: qui si decide dove
 * compaiono, come si pagano e fin dove arrivano. Visualizzazioni e clic
 * non si toccano a mano, li conta il sito.
 */
class AdminAdvertisementController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.advertisements.index', [
            'campaigns' => Advertisement::query()
                ->with('advertiser')
                ->when($request->integer('inserzionista'), fn ($q, $id) => $q->where('advertiser_id', $id))
                ->when($request->string('cerca')->toString(), fn ($q, $term) => $q->where('name', 'like', "%$term%"))
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'advertisers' => Advertiser::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.advertisements.form', $this->formData(new Advertisement([
            'status' => 1,
            'billing' => 'period',
            'advertiser_id' => $request->integer('inserzionista') ?: null,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $campaign = Advertisement::create($this->attributes($request, $data));

        return redirect()->route('admin.advertisements.show', $campaign)->with('success', __('Campagna creata.'));
    }

    public function show(Advertisement $advertisement): View
    {
        return view('admin.advertisements.show', [
            'campaign' => $advertisement->load('advertiser'),
            'stats' => $advertisement->recentStats(),
        ]);
    }

    public function edit(Advertisement $advertisement): View
    {
        return view('admin.advertisements.form', $this->formData($advertisement));
    }

    public function update(Request $request, Advertisement $advertisement): RedirectResponse
    {
        $data = $this->validated($request, $advertisement);
        $previousImage = $advertisement->img;

        $advertisement->update($this->attributes($request, $data));

        if ($previousImage && $previousImage !== $advertisement->img) {
            app(ImageStore::class)->delete($previousImage);
        }

        return back()->with('success', __('Campagna aggiornata.'));
    }

    public function destroy(Advertisement $advertisement): RedirectResponse
    {
        if ($advertisement->img) {
            app(ImageStore::class)->delete($advertisement->img);
        }

        $advertisement->delete();

        return redirect()->route('admin.advertisements.index')->with('success', __('Campagna eliminata.'));
    }

    private function validated(Request $request, ?Advertisement $campaign = null): array
    {
        $billing = $request->input('billing');

        return $request->validate([
            'advertiser_id' => ['nullable', 'exists:advertisers,id'],
            'name' => ['required', 'string', 'max:255'],
            'link' => ['required', 'url:http,https', 'max:2000'],
            'image' => [$campaign?->img ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288'],
            'locations' => ['required', 'array', 'min:1'],
            'locations.*' => [Rule::in(array_keys(Placements::all()))],
            'billing' => ['required', Rule::in(array_keys(Advertisement::BILLING))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => array_merge(
                ['nullable', 'date', Rule::requiredIf($billing === 'period')],
                $request->filled('starts_at') ? ['after_or_equal:starts_at'] : []
            ),
            'max_impressions' => ['nullable', 'integer', 'min:1', Rule::requiredIf($billing === 'impressions')],
            'max_clicks' => ['nullable', 'integer', 'min:1', Rule::requiredIf($billing === 'clicks')],
            'target_domains' => ['nullable', 'array'],
            'target_domains.*' => ['integer', function (string $attribute, mixed $value, \Closure $fail) {
                if ((int) $value !== AdContext::MAIN_SITE && ! Domain::whereKey($value)->exists()) {
                    $fail(__('Dominio sconosciuto.'));
                }
            }],
            'target_cities' => ['nullable', 'string', 'max:1000'],
            'target_categories' => ['nullable', 'array'],
            'target_categories.*' => ['integer', 'exists:company_categories,id'],
            'status' => ['boolean'],
        ], [], [
            'locations' => 'posizioni',
            'ends_at' => 'fine',
            'max_impressions' => 'visualizzazioni acquistate',
            'max_clicks' => 'clic acquistati',
        ]);
    }

    private function attributes(Request $request, array $data): array
    {
        $attributes = Arr::only($data, [
            'advertiser_id', 'name', 'link', 'locations', 'billing', 'starts_at', 'ends_at', 'max_impressions', 'max_clicks',
        ]) + [
            'status' => $request->boolean('status') ? 1 : 0,
            'target_domains' => array_values(array_map('intval', $data['target_domains'] ?? [])) ?: null,
            'target_categories' => array_values(array_map('intval', $data['target_categories'] ?? [])) ?: null,
            'target_cities' => self::cities($data['target_cities'] ?? null) ?: null,
        ];

        // La fine e' compresa: la campagna vale fino a sera.
        if (filled($attributes['ends_at'] ?? null)) {
            $attributes['ends_at'] = \Illuminate\Support\Carbon::parse($attributes['ends_at'])->endOfDay();
        }

        if ($request->hasFile('image')) {
            $attributes['img'] = app(ImageStore::class)->store($request->file('image'), 'advertisements', 'advertisement');
        }

        return $attributes;
    }

    /** "Roma, Ostia; Pomezia" diventa ["Roma", "Ostia", "Pomezia"]. */
    private static function cities(?string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) $value)))));
    }

    private function formData(Advertisement $campaign): array
    {
        return [
            'campaign' => $campaign,
            'advertisers' => Advertiser::orderBy('name')->get(['id', 'name', 'company_id']),
            'placements' => Placements::all(),
            'billing' => Advertisement::BILLING,
            'domains' => Domain::orderBy('name')->get(['id', 'name', 'domain']),
            'categories' => CompanyCategory::orderBy('name')->get(['id', 'name', 'parent_id']),
        ];
    }
}
