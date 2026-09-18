<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Advertiser;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Registrazione degli inserzionisti esterni.
 *
 * Nasce l'accesso e il profilo inserzionista; le campagne le crea
 * l'amministrazione, che le fattura a parte. L'inserzionista vede
 * statistiche e scadenze nella sua area.
 */
class AdvertiserRegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.advertiser-register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'user_type' => 'advertiser',
                'is_active' => true,
            ]);

            Advertiser::create([
                'user_id' => $user->id,
                'name' => $data['business_name'],
                'contact_name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'vat_number' => $data['vat_number'] ?? null,
                'is_active' => true,
            ]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        if (! $user->sendVerificationCode()) {
            return redirect()->route('verification.show')
                ->with('error', __("Account creato, ma la mail con il codice non e' partita: chiedine un altro qui sotto."));
        }

        return redirect()->route('verification.show')
            ->with('success', __('Ti abbiamo inviato un codice di verifica.'));
    }
}
