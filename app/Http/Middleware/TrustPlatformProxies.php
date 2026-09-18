<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Dietro Caddy l'app riceve richieste in http dalla macchina stessa: il
 * protocollo, il dominio e l'IP veri arrivano negli X-Forwarded-*. Si
 * accettano solo dai proxy in `ksm.server.trusted_proxies`; da chiunque
 * altro vengono ignorati, cosi' un header scritto a mano non cambia il
 * dominio servito ne' l'IP registrato.
 *
 * La lista si legge a ogni richiesta dalla configurazione, che al
 * momento della registrazione dei middleware non e' ancora caricata.
 */
class TrustPlatformProxies extends TrustProxies
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    protected function proxies()
    {
        return config('ksm.server.trusted_proxies');
    }
}
