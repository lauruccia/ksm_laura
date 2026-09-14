<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertiser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Inserzionisti del circuito banner.
 *
 * Un'azienda del sito diventa inserzionista e vede le campagne nella sua
 * area. Un esterno di solito si registra da solo; qui gli si puo' anche
 * creare o cambiare l'accesso.
 */
class AdminAdvertiserController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.advertisers.index', [
            'advertisers' => Advertiser::query()
                ->with(['company:id,name', 'user:id,email'])
                ->withCount('advertisements')
                ->when($request->string('cerca')->toString(), fn ($q, $term) => $q->where(fn ($q) => $q
                    ->where('name', 'like', "%$term%")
                    ->orWhere('email', 'like', "%$term%")))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.advertisers.form', ['advertiser' => new Advertiser(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $advertiser = DB::transaction(function () use ($data) {
            $advertiser = Advertiser::create($this->attributes($data));
            $this->syncAccount($advertiser, $data);

            return $advertiser;
        });

        return redirect()->route('admin.advertisers.edit', $advertiser)->with('success', __('Inserzionista creato.'));
    }

    public function show(Advertiser $advertiser): RedirectResponse
    {
        return redirect()->route('admin.advertisers.edit', $advertiser);
    }

    public function edit(Advertiser $advertiser): View
    {
        return view('admin.advertisers.form', ['advertiser' => $advertiser->load('company', 'user')]);
    }

    public function update(Request $request, Advertiser $advertiser): RedirectResponse
    {
        $data = $this->validated($request, $advertiser);

        DB::transaction(function () use ($advertiser, $data) {
            $advertiser->update($this->attributes($data));
            $this->syncAccount($advertiser, $data);
        });

        return back()->with('success', __('Inserzionista aggiornato.'));
    }

    /** Con campagne non si elimina: si sospende, e le statistiche restano. */
    public function destroy(Advertiser $advertiser): RedirectResponse
    {
        if ($advertiser->advertisements()->exists()) {
            return back()->with('error', __('L inserzionista ha campagne: sospendilo invece di eliminarlo.'));
        }

        $advertiser->delete();

        return redirect()->route('admin.advertisers.index')->with('success', __('Inserzionista eliminato.'));
    }

    private function validated(Request $request, ?Advertiser $advertiser = null): array
    {
        // La casella di ricerca manda l'etichetta dell'azienda: conta l'id in fondo.
        if (preg_match('/#(\d+)\s*$/', (string) $request->input('company'), $match)) {
            $request->merge(['company_id' => $match[1]]);
        }

        $isCompany = $request->input('kind') === 'company';

        $data = $request->validate([
            'kind' => ['required', Rule::in(['company', 'external'])],
            'company_id' => [
                Rule::requiredIf($isCompany), 'nullable', 'exists:companies,id',
                Rule::unique('advertisers', 'company_id')->ignore($advertiser?->id),
            ],
            'name' => [Rule::requiredIf(! $isCompany), 'nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'login_email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($advertiser?->user_id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ], [
            'company_id.required' => __('Scegli l\'azienda fra quelle proposte dalla ricerca.'),
            'company_id.unique' => __('Questa azienda e gia un inserzionista.'),
        ]);

        if (! $isCompany && filled($data['login_email'] ?? null) && ! $advertiser?->user_id && blank($data['password'] ?? null)) {
            throw ValidationException::withMessages(['password' => __('Per creare l accesso serve una password.')]);
        }

        return $data;
    }

    private function attributes(array $data): array
    {
        $company = ($data['kind'] === 'company') ? Company::find($data['company_id']) : null;

        return [
            'company_id' => $company?->id,
            'name' => filled($data['name'] ?? null) ? $data['name'] : $company?->name,
            'contact_name' => $data['contact_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'vat_number' => $data['vat_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    /**
     * Accesso dell'inserzionista esterno.
     *
     * Un'azienda del sito entra con l'accesso della sua azienda: niente
     * secondo accesso. Un esterno ne ha uno, creato qui o alla registrazione.
     */
    private function syncAccount(Advertiser $advertiser, array $data): void
    {
        if ($advertiser->isCompany() || blank($data['login_email'] ?? null)) {
            return;
        }

        $user = $advertiser->user ?? new User([
            'name' => $advertiser->contact_name ?: $advertiser->name,
            'user_type' => 'advertiser',
            'is_active' => true,
        ]);

        $user->email = $data['login_email'];

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        if (! $user->exists) {
            // Creato qui, l'indirizzo lo garantisce l'amministrazione.
            $user->email_verified_at = now();
        }

        $user->save();

        if ($advertiser->user_id !== $user->id) {
            $advertiser->update(['user_id' => $user->id]);
        }
    }
}
