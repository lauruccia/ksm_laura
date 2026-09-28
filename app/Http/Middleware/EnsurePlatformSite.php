<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sezioni che esistono solo sul sito principale: piani, iscrizione delle
 * aziende e degli inserzionisti, attivazione, abbonamento, area azienda,
 * area inserzionista e amministrazione.
 *
 * Su un dominio della rete o sul dominio di un'azienda rispondono 404,
 * come se non ci fossero: quei siti non devono rivelare la piattaforma.
 * Va dopo ResolveTenant, che decide su quale sito si e'.
 */
class EnsurePlatformSite
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(TenantContext::class)->isPlatformSite(), 404);

        return $next($request);
    }
}
