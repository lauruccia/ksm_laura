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
        $host = $this->normaliseHost(
            $request->header('X-Forwarded-Host') ?? $request->getHost()
        );

        $context = app(TenantContext::class);
        $context->reset();

        if (in_array($host, config('ksm.platform_hosts'), true)) {
            $context->reset();

            return $next($request);
        }

        $company = Company::query()
            ->where('custom_domain', $host)
            ->where('is_active', true)
            ->first();

        if ($company) {
            $context->useCompany($company);

            return $next($request);
        }

        $domain = Domain::query()
            ->where('domain', $host)
            ->where('is_active', true)
            ->first();

        if ($domain) {
            $context->useDomain($domain);
        }

        return $next($request);
    }

    private function normaliseHost(string $host): string
    {
        $host = strtolower($host);
        $host = preg_replace('/:\d+$/', '', $host);

        return preg_replace('/^www\./', '', $host);
    }
}
