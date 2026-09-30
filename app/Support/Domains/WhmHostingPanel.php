<?php

namespace App\Support\Domains;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * I domini dell'app sulla WHM del rivenditore.
 *
 * Senza pacchetto proxy il dominio si parcheggia sull'account che ospita
 * l'app (permesso park-dns, come WHM -> Parcheggia un dominio): tutto
 * arriva nella cartella del dominio principale, che porta all'app.
 *
 * Con un pacchetto proxy (KSM_WHM_PROXY_PLAN) al dominio si da' invece un
 * account cPanel tutto suo, minuscolo, con il suo certificato: quello
 * dell'account dell'app si ferma a 100 nomi. Nell'account c'e' un
 * index.php che inoltra ogni richiesta all'app e passa il dominio vero in
 * X-Forwarded-Host. Per l'app il dominio e' collegato come gli altri:
 * basta che KSM_TRUSTED_PROXIES contenga l'IP del server.
 *
 * In entrambi i casi cPanel crea la zona DNS e AutoSSL fa il certificato
 * quando il DNS punta al server.
 *
 * Togliere un dominio (Elimina in Amministrazione) chiude l'account proxy,
 * ma solo se e' davvero un proxy di KSM (index.php con il segno
 * PROXY_MARK), oppure toglie il parcheggio dall'account dell'app. Il
 * dominio principale dell'app e gli altri account non si toccano mai.
 */
class WhmHostingPanel implements HostingPanel
{
    /** Il segno nell'index.php dei proxy: senza, un account non si chiude. */
    public const PROXY_MARK = 'KSM domain proxy';

    /** Dominio principale e domini gia' presenti sull'account, letti una volta per istanza. */
    private ?string $mainDomain = null;

    private ?array $known = null;

    /** Account cPanel a cui chiedere il certificato: quello dell'app e i proxy toccati. */
    private array $certificateUsers = [];

    private array $certificateRequested = [];

    public function __construct(
        private readonly string $url,
        private readonly string $reseller,
        private readonly string $token,
        private readonly string $account,
        private readonly ?string $proxyPlan = null,
        private readonly ?string $proxyTarget = null,
        private readonly ?string $contactEmail = null,
    ) {
        $this->certificateUsers[] = $account;
    }

    public function ensure(string $host): ?string
    {
        $host = strtolower($host);

        try {
            $this->known ??= $this->domains();

            if (in_array($host, $this->known, true)) {
                return null;
            }

            // Con un pacchetto proxy ogni dominio nuovo ha un account suo, senza parcheggio:
            // il certificato dell'account dell'app non contiene piu' di 100 nomi, e un
            // dominio parcheggiato oltre quel tetto resterebbe senza https.
            if ($this->usesProxyAccounts()) {
                $user = $this->accountFor($host);

                return $user ? $this->installProxy($user, $host) : $this->createProxyAccount($host);
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

    public function remove(string $host): ?string
    {
        $host = strtolower($host);

        try {
            $this->known ??= $this->domains();

            if ($host === strtolower((string) $this->mainDomain)) {
                return "Il dominio principale dell'account {$this->account} non si toglie.";
            }

            if (in_array($host, $this->known, true)) {
                return $this->unpark($host);
            }

            $user = $this->accountFor($host);

            if (! $user) {
                return null;
            }

            if ($user === $this->account) {
                return "{$host} sta sull'account dell'app: non si toglie da qui.";
            }

            $index = (string) $this->uapi($user, 'Fileman', 'get_file_content', ['dir' => 'public_html', 'file' => 'index.php'])
                ->json('result.data.content', '');

            if (! str_contains($index, self::PROXY_MARK)) {
                return "L'account {$user} di {$host} non e' un proxy di KSM: va tolto a mano.";
            }

            $response = $this->http()->asForm()->post('/json-api/removeacct', ['api.version' => 1, 'username' => $user]);

            if (! $response->successful() || ! $response->json('metadata.result')) {
                return "WHM non ha chiuso l'account {$user}: ".($response->json('metadata.reason') ?: 'HTTP '.$response->status());
            }
        } catch (Throwable $e) {
            return 'WHM non risponde: '.$e->getMessage();
        }

        return null;
    }

    /** Toglie un dominio parcheggiato sull'account dell'app (cPanel API 2, Park::unpark). */
    private function unpark(string $host): ?string
    {
        $response = $this->http()->get('/json-api/cpanel', [
            'cpanel_jsonapi_user' => $this->account,
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'Park',
            'cpanel_jsonapi_func' => 'unpark',
            'domain' => $host,
        ]);

        $result = $response->json('cpanelresult.data.0', []);

        if (! $response->successful() || empty($result['result'])) {
            return 'WHM non ha tolto il parcheggio: '.($result['reason'] ?? $response->json('cpanelresult.error') ?? 'HTTP '.$response->status());
        }

        $this->known = array_values(array_diff($this->known, [$host]));

        return null;
    }

    /** Prova la connessione dall'Amministrazione: null se va, altrimenti cosa non va. */
    public function probe(): ?string
    {
        try {
            $domains = $this->domains();

            if ($this->usesProxyAccounts()) {
                $plans = array_column((array) $this->http()->get('/json-api/listpkgs', ['api.version' => 1])->throw()->json('data.pkg', []), 'name');

                if (! in_array($this->proxyPlan, $plans, true)) {
                    return "Il pacchetto {$this->proxyPlan} non esiste tra quelli del rivenditore.";
                }
            }
        } catch (Throwable $e) {
            return 'WHM non risponde: '.$e->getMessage();
        }

        return null;
    }

    public function requestCertificate(): void
    {
        foreach (array_unique($this->certificateUsers) as $user) {
            if (isset($this->certificateRequested[$user])) {
                continue;
            }

            $this->certificateRequested[$user] = true;

            try {
                $this->uapi($user, 'SSL', 'start_autossl_check');
            } catch (Throwable) {
                // AutoSSL passa comunque ogni notte.
            }
        }
    }

    private function usesProxyAccounts(): bool
    {
        return filled($this->proxyPlan) && filled($this->proxyTarget);
    }

    /** Un account del rivenditore con il dominio come principale, o null. */
    private function accountFor(string $host): ?string
    {
        $accounts = (array) $this->http()->get('/json-api/listaccts', [
            'api.version' => 1,
            'searchtype' => 'domain',
            'search' => '^'.preg_quote($host).'$',
        ])->throw()->json('data.acct', []);

        foreach ($accounts as $account) {
            if (strtolower($account['domain'] ?? '') === $host) {
                return $account['user'];
            }
        }

        return null;
    }

    private function createProxyAccount(string $host): ?string
    {
        $user = $this->freeUsername($host);

        $response = $this->http()->asForm()->post('/json-api/createacct', array_filter([
            'api.version' => 1,
            'username' => $user,
            'domain' => $host,
            'plan' => $this->proxyPlan,
            // Non serve a nessuno: l'account lo gestisce KSM con il token del rivenditore.
            'password' => Str::password(32),
            'contactemail' => $this->contactEmail,
        ]));

        if (! $response->successful() || ! $response->json('metadata.result')) {
            $reason = $response->json('metadata.reason') ?: 'HTTP '.$response->status();

            // Il messaggio della WHM non dice cosa fare: il pacchetto e' quello di Server e hosting (o del .env).
            return 'Account proxy non creato: '.$reason
                .(str_contains(strtolower($reason), 'package') ? " (pacchetto \"{$this->proxyPlan}\": controllalo in Domini, Server e hosting, con Prova la connessione)" : '');
        }

        return $this->installProxy($user, $host);
    }

    /** Scrive .htaccess e index.php nella public_html dell'account proxy. */
    private function installProxy(string $user, string $host): ?string
    {
        $files = [
            '.htaccess' => file_get_contents(resource_path('domain-proxy/htaccess.stub')),
            'index.php' => str_replace(
                ['{{TARGET}}', '{{SERVER_IP}}'],
                [rtrim((string) $this->proxyTarget, '/'), (string) (config('ksm.server.ips')[0] ?? '')],
                file_get_contents(resource_path('domain-proxy/index.php.stub')),
            ),
        ];

        foreach ($files as $name => $content) {
            $response = $this->uapi($user, 'Fileman', 'save_file_content', [
                'dir' => 'public_html',
                'file' => $name,
                'content' => $content,
            ]);

            if (! $response->successful() || ! $response->json('result.status')) {
                $errors = implode(' ', (array) $response->json('result.errors', []));

                return "Account proxy {$user} creato, ma {$name} non e' stato scritto: ".($errors ?: 'HTTP '.$response->status());
            }
        }

        $this->known[] = $host;
        $this->certificateUsers[] = $user;
        $this->requestCertificate();

        return null;
    }

    /**
     * Nome utente cPanel dal dominio: lettere e cifre, inizia con una lettera,
     * al massimo 16 caratteri, mai "test" all'inizio; se e' preso si aggiunge un numero.
     */
    private function freeUsername(string $host): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', Str::beforeLast($host, '.'));
        $base = preg_replace('/^[0-9]+/', '', $base) ?: 'dominio';
        $base = Str::startsWith($base, 'test') ? 'd'.$base : $base;
        $base = substr($base, 0, 14);

        for ($i = 0; $i < 100; $i++) {
            $candidate = $i === 0 ? $base : substr($base, 0, 14 - strlen((string) $i)).$i;

            $taken = (array) $this->http()->get('/json-api/verify_new_username', [
                'api.version' => 1,
                'user' => $candidate,
            ])->json('metadata', []);

            if (($taken['result'] ?? 0) == 1) {
                return $candidate;
            }
        }

        return substr($base, 0, 8).Str::lower(Str::random(6));
    }

    /** @return list<string> */
    private function domains(): array
    {
        $data = (array) $this->uapi($this->account, 'DomainInfo', 'list_domains')->throw()->json('result.data', []);
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

    /** Una funzione UAPI eseguita come un account del rivenditore, attraverso la WHM. */
    private function uapi(string $user, string $module, string $function, array $params = []): Response
    {
        $query = [
            'cpanel_jsonapi_user' => $user,
            'cpanel_jsonapi_apiversion' => 3,
            'cpanel_jsonapi_module' => $module,
            'cpanel_jsonapi_func' => $function,
        ];

        // Il contenuto dei file non sta in un indirizzo: va nel corpo.
        return $params
            ? $this->http()->asForm()->post('/json-api/cpanel', $query + $params)
            : $this->http()->get('/json-api/cpanel', $query);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->url, '/'))
            ->withHeaders(['Authorization' => "whm {$this->reseller}:{$this->token}"])
            ->acceptJson()
            ->timeout(60);
    }
}
