<?php

namespace App\Support\Domains;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Verifica se un dominio e' collegato alla piattaforma.
 *
 * Due passi, nell'ordine in cui il cliente li fa: prima il record DNS
 * verso il server, poi il certificato, che il server web emette solo
 * quando il DNS e' gia' giusto. La verifica e' la stessa sul VPS e su
 * cPanel: cambia chi emette il certificato, non come lo si controlla.
 */
class DomainConnectionChecker
{
    public function __construct(
        private readonly DnsResolver $dns = new SystemDnsResolver,
        private readonly TlsProbe $tls = new StreamTlsProbe,
    ) {
    }

    public function check(string $host): DomainCheck
    {
        if (! HostName::isValid($host)) {
            return new DomainCheck(false, false, 'Nome di dominio non valido.');
        }

        $ips = (array) config('ksm.server.ips');
        $cnameTarget = config('ksm.server.cname');

        if (! $ips && ! $cnameTarget) {
            return new DomainCheck(false, false, 'Indirizzo del server non configurato: manca KSM_SERVER_IPS.');
        }

        $addresses = $this->dns->addresses($host);
        $cname = $this->dns->cname($host);

        $pointsHere = array_intersect($addresses, $ips) !== []
            || ($cnameTarget && $cname && rtrim(strtolower($cname), '.') === strtolower($cnameTarget));

        if (! $pointsHere) {
            return new DomainCheck(false, false, $addresses
                ? 'Il dominio punta a '.implode(', ', $addresses).', non al server.'
                : 'Nessun record DNS trovato per il dominio.');
        }

        $tlsError = $this->tls->check($host);

        return new DomainCheck(true, $tlsError === null, $tlsError ? 'Certificato non ancora valido: '.$tlsError : null);
    }

    /**
     * Verifica e salva l'esito sul dominio o sull'azienda.
     *
     * Le date restano quelle della prima verifica riuscita: dicono da
     * quando il dominio funziona, non quando e' stato guardato l'ultima volta.
     */
    public function refresh(Model $model, string $host): DomainCheck
    {
        $result = $this->check($host);

        $model->forceFill([
            'dns_verified_at' => $result->dns ? ($model->dns_verified_at ?? now()) : null,
            'ssl_verified_at' => $result->ssl ? ($model->ssl_verified_at ?? now()) : null,
            'domain_checked_at' => now(),
            'domain_error' => $result->error ? Str::limit($result->error, 250) : null,
        ])->saveQuietly();

        return $result;
    }
}
