<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Domain;
use App\Support\Domains\HostingPanel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Toglie dal pannello dell'hosting un dominio eliminato o cambiato: sulla
 * WHM chiude l'account proxy o toglie il parcheggio (vedi WhmHostingPanel).
 *
 * In coda come la registrazione, perche' chiudere un account fa ripartire
 * Apache. Se un altro dominio o un'azienda usa ancora lo stesso indirizzo,
 * non si tocca niente.
 */
class RemoveDomainFromHostingPanel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly string $host) {}

    /** Attese fra un tentativo e l'altro, in secondi. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(HostingPanel $panel): void
    {
        $host = strtolower($this->host);

        if (Domain::query()->whereRaw('LOWER(domain) = ?', [$host])->exists()
            || Company::query()->whereRaw('LOWER(custom_domain) = ?', [$host])->exists()) {
            return;
        }

        if ($error = $panel->remove($host)) {
            Log::warning("Dominio {$host} non tolto dal pannello: {$error}");
        }
    }
}
