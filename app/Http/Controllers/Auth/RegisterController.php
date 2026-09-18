<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Support\AuthReturn;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Le due registrazioni.
 *
 * Il privato apre un account per comprare: pochi campi e via. L'azienda
 * apre l'account del referente e puo' gia' indicare il piano; profilo e
 * pagamento del piano arrivano dopo la verifica dell'indirizzo. Il tipo di
 * account lo decide la rotta, mai un campo del modulo.
 */
class RegisterController extends Controller
{
    /** Errori della registrazione dentro l'acquisto: sacca sua, separata da quella dell'accesso. */
    public const BAG = 'registrazione';

    public function choose(Request $request): View|RedirectResponse
    {
        // Dai vecchi link dei piani: chi ha gia' scelto un piano e' un'azienda.
        if ($request->filled('piano')) {
            return redirect()->route('register.vendor', ['piano' => $request->string('piano')->toString()]);
        }

        return view('auth.register-choose');
    }

    public function showBuyer(): View
    {
        return view('auth.register-buyer');
    }

    public function showVendor(Request $request): View
    {
        $plans = Plan::active()->byRank()->get();
        $chosen = $request->string('piano')->toString();

        return view('auth.register-vendor', [
            'plans' => $plans,
            // Un piano sconosciuto nel link vale "decido dopo".
            'chosen' => $plans->contains('slug', $chosen) ? $chosen : '',
        ]);
    }

    public function storeBuyer(Request $request): RedirectResponse
    {
        $ritorno = $request->string('ritorno')->toString();
        $inline = AuthReturn::known($ritorno);

        $data = Validator::make($request->all(), $this->accountRules())
            ->validateWithBag($inline ? self::BAG : 'default');

        $this->register($request, $data + ['user_type' => 'buyer']);

        // Registrazione fatta durante l'acquisto: si torna subito dov'era
        // rimasto l'ordine. Il codice di verifica resta nella posta e si usa
        // quando fa comodo.
        if ($inline) {
            return redirect()->to(AuthReturn::url($ritorno, route('home')))
                ->with('success', __('Account creato. Ti abbiamo inviato un codice di verifica per email: puoi intanto completare l\'ordine.'));
        }

        return redirect()->route('verification.show')
            ->with('success', __('Ti abbiamo inviato un codice di verifica.'));
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $data = $request->validate($this->accountRules() + [
            'piano' => ['nullable', 'exists:plans,slug'],
        ]);

        $this->register($request, collect($data)->except('piano')->all() + ['user_type' => 'vendor']);

        // Il piano scelto qui torna gia' selezionato a fine registrazione,
        // dopo la verifica dell'indirizzo e la creazione del profilo.
        if (filled($data['piano'] ?? null)) {
            $request->session()->put('piano_scelto', $data['piano']);
        }

        return redirect()->route('verification.show')
            ->with('success', __('Ti abbiamo inviato un codice di verifica: dopo la conferma crei il profilo della tua azienda.'));
    }

    private function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    private function register(Request $request, array $attributes): User
    {
        $user = User::create($attributes);

        event(new Registered($user));
        Auth::login($user);

        return $user;
    }
}
