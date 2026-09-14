<?php

namespace App\Http\Middleware;

use App\Support\PlanCapabilities;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chiude una sezione dell'area azienda a chi non ha il piano adatto.
 *
 * Si usa col nome della voce: `EnsurePlanAllows::class.':shop'`. Chi non
 * ce l'ha non viene respinto con un errore, viene mandato a cambiare piano.
 */
class EnsurePlanAllows
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $company = $request->user()?->company;

        if (! $company?->allows($capability)) {
            return redirect()->route('subscription.index')->with(
                'error',
                __('Il tuo piano non comprende: :voce', ['voce' => PlanCapabilities::label($capability)])
            );
        }

        return $next($request);
    }
}
