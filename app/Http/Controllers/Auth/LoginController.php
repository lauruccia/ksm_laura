<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AuthReturn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Errori del modulo dentro l'acquisto: sacca sua, per non confonderli con quelli della registrazione. */
    public const BAG = 'accesso';

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $ritorno = $request->string('ritorno')->toString();
        $inline = AuthReturn::known($ritorno);
        $bag = $inline ? self::BAG : 'default';

        $credentials = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ])->validateWithBag($bag);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('Credenziali non valide.'),
            ])->errorBag($bag);
        }

        // Il carrello sta in sessione e sopravvive al cambio di identificativo:
        // chi entra dalla cassa ritrova l'ordine dov'era.
        $request->session()->regenerate();

        return $inline
            ? redirect()->to(AuthReturn::url($ritorno, route('home')))
            : redirect()->intended($this->homeFor($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function homeFor(Request $request): string
    {
        $user = $request->user();

        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isVendor() && $user->hasActiveCompany() => route('vendor.dashboard'),
            $user->isVendor() && ! $user->company => route('onboarding.create'),
            $user->isVendor() => route('subscription.index'),
            $user->isAdvertiser() => route('advertiser.dashboard'),
            default => route('home'),
        };
    }
}
