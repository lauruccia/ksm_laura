<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->email_verified_at) {
            return redirect()->to($this->nextStep($request));
        }

        return view('auth.verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();

        $valid = $user->verification_code === $request->string('code')->toString()
            && $user->verification_code_expires_at?->isFuture();

        if (! $valid) {
            return back()->with('error', __('Codice non valido o scaduto.'));
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();

        return redirect()->to($this->nextStep($request))->with('success', __('Indirizzo verificato.'));
    }

    /** L'azienda appena verificata deve creare il profilo e scegliere il piano. */
    private function nextStep(Request $request): string
    {
        $user = $request->user();

        return match (true) {
            $user->isVendor() && ! $user->company => route('onboarding.create'),
            $user->isVendor() && ! $user->hasActiveCompany() => route('subscription.index'),
            $user->isAdvertiser() => route('advertiser.dashboard'),
            default => route('home'),
        };
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->user()->sendVerificationCode()) {
            return back()->with('error', __('Non siamo riusciti a inviare la mail. Riprova tra poco.'));
        }

        return back()->with('success', __('Nuovo codice inviato.'));
    }
}
