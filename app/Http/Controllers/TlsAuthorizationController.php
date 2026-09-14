<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Domain;
use App\Support\Domains\HostName;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Dice al server web se puo' chiedere un certificato per un dominio.
 *
 * Sul VPS, con Caddy e "on demand TLS", il certificato nasce alla prima
 * visita del dominio. Prima di chiederlo a Let's Encrypt, Caddy chiama
 * questo indirizzo: 200 se il dominio e' della piattaforma, 404 se no.
 * Senza il controllo chiunque puntasse un dominio al server farebbe
 * emettere certificati a nostro nome, fino ai limiti di Let's Encrypt.
 *
 * Risponde solo al server stesso: da fuori dice 403.
 */
class TlsAuthorizationController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(in_array($request->ip(), (array) config('ksm.server.tls_ask_ips'), true), 403);

        $host = HostName::normalize($request->query('domain'));

        $known = $host !== null && (
            in_array($host, (array) config('ksm.platform_hosts'), true)
            || Domain::where('domain', $host)->where('is_active', true)->exists()
            || Company::where('custom_domain', $host)->exists()
        );

        return response($known ? 'ok' : 'dominio sconosciuto', $known ? 200 : 404);
    }
}
