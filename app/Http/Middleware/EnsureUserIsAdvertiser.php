<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Area inserzionista: solo per chi ha un profilo inserzionista attivo. */
class EnsureUserIsAdvertiser
{
    public function handle(Request $request, Closure $next): Response
    {
        $advertiser = $request->user()?->advertiser;

        abort_unless($advertiser, 403, __('Area riservata agli inserzionisti.'));
        abort_unless($advertiser->is_active, 403, __('Profilo inserzionista sospeso. Rivolgiti all amministrazione.'));

        return $next($request);
    }
}
