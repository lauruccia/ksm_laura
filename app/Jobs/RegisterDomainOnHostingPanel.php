<?php

namespace App\Jobs;

use App\Support\Domains\DomainConnectionChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Porta un dominio sul pannello dell'hosting e ne verifica il collegamento.
 *
 * Gira in coda, non dentro la richiesta: sulla WHM creare un account o
 * parcheggiare un dominio fa ripartire Apache, e la pagina che ha salvato
 * il dominio si vedrebbe chiudere la connessione (ERR_CONNECTION_CLOSED).
 * Il giro della coda parte ogni minuto dal cron di schedule:run.
 */
class RegisterDomainOnHostingPanel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @param class-string<Model> $modelClass */
    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelId,
        public readonly string $host,
    ) {}

    /** Attese fra un tentativo e l'altro, in secondi. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(DomainConnectionChecker $checker): void
    {
        $model = $this->modelClass::find($this->modelId);

        // Cancellato o cambiato nel frattempo: quel dominio non serve piu'.
        if (! $model || $model->{$model->domainColumn()} !== $this->host) {
            return;
        }

        $checker->refresh($model, $this->host);
    }
}
