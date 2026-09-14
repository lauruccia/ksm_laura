<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isVendor()) {
            abort(403, __('Serve un profilo azienda attivo.'));
        }

        // Chi non ha ancora l'azienda, o ha il piano scaduto, non viene
        // respinto: viene rimandato al passo che gli manca.
        if (! $user->company) {
            return redirect()->route('onboarding.create');
        }

        if (! $user->hasActiveCompany()) {
            return redirect()->route('subscription.index')
                ->with('error', __('Attiva un piano per usare l area azienda.'));
        }

        return $next($request);
    }
}
