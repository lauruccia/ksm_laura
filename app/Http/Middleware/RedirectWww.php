<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un indirizzo solo per sito: www.dominio.it rimanda con un 301 a
 * dominio.it, stesso percorso e stessi parametri. Senza, i motori di
 * ricerca vedono due siti con le stesse pagine.
 *
 * Solo GET e HEAD: un modulo inviato a www non va perso in un redirect.
 */
class RedirectWww
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // Un'azienda puo' volere il suo dominio con il www (force_www): allora resta com'e'.
        if (str_starts_with($host, 'www.') && $request->isMethodSafe()
            && ! Company::query()->where('custom_domain', substr($host, 4))->where('force_www', true)->exists()) {
            $port = $request->getPort();
            $default = $request->isSecure() ? 443 : 80;

            return redirect()->to(
                $request->getScheme().'://'.substr($host, 4).($port && $port !== $default ? ':'.$port : '').$request->getRequestUri(),
                301
            );
        }

        return $next($request);
    }
}
