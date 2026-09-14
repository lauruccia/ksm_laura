<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta d'ingresso del pannello: dice solo chi puo' entrare.
 *
 * Cosa puo' fare una volta dentro lo decidono i permessi del ruolo,
 * controllati rotta per rotta con `can:`.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isAdmin()) {
            abort(403, __('Area riservata.'));
        }

        // Un collaboratore sospeso non entra, anche se il ruolo c'e' ancora.
        if (! $user->is_active) {
            abort(403, __('Accesso sospeso. Rivolgiti a un amministratore.'));
        }

        // Senza ruolo non concede nulla: meglio dirlo che mostrare
        // un pannello vuoto in cui ogni voce risponde 403.
        if (! $user->role) {
            abort(403, __('Nessun ruolo assegnato. Rivolgiti a un amministratore.'));
        }

        return $next($request);
    }
}
