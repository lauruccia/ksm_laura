<?php

namespace App\Support\Domains;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * I domini dell'app parcheggiati sull'account che la ospita, dalla WHM del rivenditore.
 *
 * Il pacchetto dell'account non ammette alias, ma il rivenditore puo'
 * parcheggiare domini sui suoi account (permesso park-dns): e' la stessa
 * operazione di WHM -> Parcheggia un dominio. Tutto arriva nella cartella
 * del dominio principale, che porta all'app. cPanel crea la zona DNS e
 * AutoSSL fa il certificato quando il DNS punta al server.
 *
 * Aggiunge e basta: togliere un dominio resta una scelta a mano.
 */
class WhmHostingPanel implements HostingPanel
{
    /** Dominio principale e domini gia' presenti sull'account, letti una volta per istanza. */
    private ?string $mainDomain = null;

    private ?array $known = null;

    private bool $certificateRequested = false;

    public function __construct(
        private readonly string $url,
        private readonly string $reseller,
        private readonly string $token,
        private readonly string $account,
    ) {
    }

    public function ensure(string $host): ?string
    {
        $host = strtolower($host);

        try {
            $this->known ??= $this->domains();

            if (in_array($host, $this->known, true)) {
                return null;
            }

            $response = $this->http()->get('/json-api/create_parked_domain_for_user', [
                'api.version' => 1,
                'domain' => $host,
                'username' => $this->account,
                'web_vhost_domain' => $this->mainDomain,
            ]);

            if (! $response->successful() || ! $response->json('metadata.result')) {
                return 'WHM non ha parcheggiato il dominio: '.($response->json('metadata.reason') ?: 'HTTP '.$response->status());
            }
        } catch (Throwable $e) {
            return 'WHM non risponde: '.$e->getMessage();
        }

        $this->known[] = $host;
        $this->requestCertificate();

        return null;
    }

    public function requestCertificate(): void
    {
        if ($this->certificateRequested) {
            return;
        }

        $this->certificateRequested = true;

        try {
            $this->uapi('SSL', 'start_autossl_check');
        } catch (Throwable) {
            // AutoSSL passa comunque ogni notte.
        }
    }

    /** @return list<string> */
    private function domains(): array
    {
        $data = (array) $this->uapi('DomainInfo', 'list_domains')->throw()->json('result.data', []);
        $this->mainDomain = $data['main_domain'] ?? null;

        if (! $this->mainDomain) {
            throw new \RuntimeException("l'account {$this->account} non risulta tra quelli del rivenditore.");
        }

        return array_map('strtolower', array_filter([
            $this->mainDomain,
            ...($data['addon_domains'] ?? []),
            ...($data['parked_domains'] ?? []),
            ...($data['sub_domains'] ?? []),
        ]));
    }

    /** Una funzione UAPI eseguita come l'account, attraverso la WHM. */
    private function uapi(string $module, string $function): \Illuminate\Http\Client\Response
    {
        return $this->http()->get('/json-api/cpanel', [
            'cpanel_jsonapi_user' => $this->account,
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => $module,
            'cpanel_jsonapi_func' => $function,
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->url, '/'))
            ->withHeaders(['Authorization' => "whm {$this->reseller}:{$this->token}"])
            ->acceptJson()
            ->timeout(60);
    }
}
