<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.register', [
            'plans' => Plan::active()->byRank()->get(),
            'chosen' => $request->string('piano')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'user_type' => ['required', 'in:buyer,vendor'],
            'piano' => ['nullable', 'exists:plans,slug'],
        ]);

        $user = User::create(collect($data)->except('piano')->all());

        // Il piano scelto qui torna gia' selezionato a fine registrazione,
        // dopo la verifica dell'indirizzo.
        if ($data['user_type'] === 'vendor' && filled($data['piano'] ?? null)) {
            $request->session()->put('piano_scelto', $data['piano']);
        }

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.show')
            ->with('success', __('Ti abbiamo inviato un codice di verifica.'));
    }
}
