<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
        $user = $request->user();

        $user->forceFill([
            'verification_code' => (string) random_int(100000, 999999),
            'verification_code_expires_at' => now()->addMinutes(30),
        ])->save();

        Mail::to($user->email)->send(new VerificationCode($user->verification_code));

        return back()->with('success', __('Nuovo codice inviato.'));
    }
}
