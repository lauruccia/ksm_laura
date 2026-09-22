<?php

namespace App\Support\Domains;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * I domini dell'app aggiunti all'account cPanel che la ospita.
 *
 * Ognuno diventa un dominio aggiuntivo con la cartella `public` dell'app:
 * un alias non basterebbe, perche' userebbe la cartella del dominio
 * principale dell'account. Se il pacchetto non ne ammette, il dominio
 * diventa un alias e ci pensa il .htaccess del dominio principale a
 * mandarlo all'app (vedi README). cPanel crea anche la zona DNS e il www,
 * e AutoSSL fa il certificato quando il DNS punta al server.
 *
 * Aggiunge e basta: togliere un dominio dal pannello resta una scelta a mano.
 */
class CpanelHostingPanel implements HostingPanel
{
    /** Domini gia' presenti sull'account, letti una volta per istanza. */
    private ?array $known = null;

    private bool $certificateRequested = false;

    public function __construct(
        private readonly string $url,
        private readonly string $user,
        private readonly string $token,
        private readonly string $docroot,
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

            $addonError = $this->api2('AddonDomain', 'addaddondomain', [
                'newdomain' => $host,
                'subdomain' => $this->subdomain($host),
                'dir' => $this->docroot,
            ]);

            // Un pacchetto senza domini aggiuntivi ammette ancora gli alias: il
            // .htaccess del dominio principale li manda poi alla cartella dell'app.
            if ($addonError && $aliasError = $this->api2('Park', 'park', ['domain' => $host])) {
                return 'cPanel non ha aggiunto il dominio: '.$addonError.' Come alias: '.$aliasError;
            }
        } catch (Throwable $e) {
            return 'cPanel non risponde: '.$e->getMessage();
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
            $this->http()->get('/execute/SSL/start_autossl_check');
        } catch (Throwable) {
            // AutoSSL passa comunque ogni notte.
        }
    }

    /** Una chiamata API2 di cPanel. Null se e' andata, altrimenti il motivo. */
    private function api2(string $module, string $function, array $params): ?string
    {
        $response = $this->http()->get('/json-api/cpanel', [
            'cpanel_jsonapi_user' => $this->user,
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => $module,
            'cpanel_jsonapi_func' => $function,
        ] + $params);

        $error = $response->json('cpanelresult.error');
        $result = $response->json('cpanelresult.data.0');

        return $response->successful() && ! $error && ($result['result'] ?? false)
            ? null
            : ($error ?: ($result['reason'] ?? 'HTTP '.$response->status()));
    }

    /** @return list<string> */
    private function domains(): array
    {
        $data = $this->http()->get('/execute/DomainInfo/list_domains')->throw()->json('data', []);

        return array_map('strtolower', array_filter([
            $data['main_domain'] ?? null,
            ...($data['addon_domains'] ?? []),
            ...($data['parked_domains'] ?? []),
            ...($data['sub_domains'] ?? []),
        ]));
    }

    /** Il sottodominio tecnico che cPanel vuole per ogni dominio aggiuntivo. */
    private function subdomain(string $host): string
    {
        return Str::limit(str_replace('.', '-', $host), 60, '');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->url, '/'))
            ->withHeaders(['Authorization' => "cpanel {$this->user}:{$this->token}"])
            ->acceptJson()
            ->timeout(60);
    }
}
