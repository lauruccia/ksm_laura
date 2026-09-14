<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('Credenziali non valide.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($request));
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
