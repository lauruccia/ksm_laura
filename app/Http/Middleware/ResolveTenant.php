<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Domain;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stabilisce il contesto della richiesta in base all'host.
 *
 * Tre casi:
 *  - dominio di piattaforma, nessun contesto;
 *  - dominio personalizzato di un'azienda, vetrina dell'azienda;
 *  - dominio configurato in tabella, vetrina filtrata per categoria o citta'.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->apply($request);

        return $next($request);
    }

    /**
     * Imposta il contesto per l'host della richiesta. Pubblico perche' lo
     * usano anche le pagine di errore di un indirizzo inesistente, che non
     * passano dai middleware delle rotte.
     */
    public function apply(Request $request): void
    {
        // getHost() tiene conto di X-Forwarded-Host solo se arriva dal proxy
        // fidato (TrustPlatformProxies): da altri l'header non conta.
        $host = $this->normaliseHost($request->getHost());

        $context = app(TenantContext::class);
        $context->reset();

        if (in_array($host, config('ksm.platform_hosts'), true)) {
            return;
        }

        $company = Company::query()
            ->where('custom_domain', $host)
            ->where('is_active', true)
            ->first();

        if ($company) {
            $context->useCompany($company);

            return;
        }

        $domain = Domain::query()
            ->where('domain', $host)
            ->where('is_active', true)
            ->first();

        if ($domain) {
            $context->useDomain($domain);
        }
    }

    private function normaliseHost(string $host): string
    {
        $host = strtolower($host);
        $host = preg_replace('/:\d+$/', '', $host);

        return preg_replace('/^www\./', '', $host);
    }
}
