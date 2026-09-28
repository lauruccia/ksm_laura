<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Domain;
use App\Support\Sites\HostDirectory;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stabilisce il contesto della richiesta in base all'host.
 *
 * Quattro casi:
 *  - dominio di piattaforma, nessun contesto;
 *  - dominio personalizzato di un'azienda, vetrina dell'azienda;
 *  - dominio configurato in tabella, vetrina filtrata per categoria o citta';
 *  - qualsiasi altro host: 404. Un dominio puntato sul server ma non
 *    registrato non deve diventare una copia di KSM.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->apply($request)) {
            return self::unknownSite();
        }

        return $next($request);
    }

    /**
     * La risposta per un host che non e' di nessun sito. Niente pagina di
     * errore: il layout mostrerebbe il marchio della piattaforma.
     */
    public static function unknownSite(): Response
    {
        return response('Sito non trovato.', 404)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Imposta il contesto per l'host della richiesta; false se l'host non
     * e' di nessun sito. Pubblico perche' lo usano anche le pagine di
     * errore di un indirizzo inesistente, che non passano dai middleware
     * delle rotte.
     */
    public function apply(Request $request): bool
    {
        // getHost() tiene conto di X-Forwarded-Host solo se arriva dal proxy
        // fidato (TrustPlatformProxies): da altri l'header non conta.
        $host = $request->getHost();

        $context = app(TenantContext::class);
        $context->reset();

        if (HostDirectory::isPlatform($host)) {
            return true;
        }

        $site = HostDirectory::find($host);

        if ($site && $site['type'] === 'company' && ($company = Company::query()->whereKey($site['id'])->where('is_active', true)->first())) {
            $context->useCompany($company);

            return true;
        }

        if ($site && $site['type'] === 'domain' && ($domain = Domain::query()->whereKey($site['id'])->where('is_active', true)->first())) {
            $context->useDomain($domain);

            return true;
        }

        return false;
    }
}
