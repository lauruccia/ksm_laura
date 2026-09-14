<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Primo passo di chi si registra come azienda: i dati dell'attivita'.
 *
 * L'azienda nasce spenta e senza piano. Diventa visibile solo quando
 * un abbonamento viene pagato.
 */
class CompanyOnboardingController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->company) {
            return redirect()->route('subscription.index');
        }

        return view('vendor.onboarding.create', [
            'categories' => CompanyCategory::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->company) {
            return redirect()->route('subscription.index');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('companies', 'slug')],
            'category_id' => ['nullable', 'exists:company_categories,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', Rule::in(config('ksm.regions'))],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'company_description' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? null) ?: $data['name']);
        $data['email'] = ($data['email'] ?? null) ?: $request->user()->email;
        $data['user_id'] = $request->user()->id;
        $data['is_active'] = false;

        Company::create($data);

        // Chi si registra come azienda arriva qui da venditore, anche
        // se in fase di registrazione aveva scelto altro.
        $request->user()->update(['user_type' => 'vendor']);

        return redirect()->route('subscription.index')
            ->with('success', __('Dati salvati. Ora scegli il piano.'));
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'azienda';
        $slug = $base;
        $suffix = 2;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
