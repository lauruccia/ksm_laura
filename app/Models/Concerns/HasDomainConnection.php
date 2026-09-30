<?php

namespace App\Models\Concerns;

use App\Jobs\RegisterDomainOnHostingPanel;
use App\Jobs\RemoveDomainFromHostingPanel;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\NoHostingPanel;
use App\Support\Sites\HostDirectory;

/**
 * Stato di collegamento di un dominio, per `Domain` e per `Company`.
 *
 * Le colonne le scrive DomainConnectionChecker; qui si leggono soltanto.
 */
trait HasDomainConnection
{
    /** Colonna che contiene il nome del dominio. */
    abstract public function domainColumn(): string;

    /** Un dominio cambiato non e' piu' quello verificato: si riparte da capo. */
    public static function bootHasDomainConnection(): void
    {
        static::saving(function (self $model) {
            if ($model->exists && $model->isDirty($model->domainColumn())) {
                $model->forceFill([
                    'dns_verified_at' => null,
                    'ssl_verified_at' => null,
                    'domain_checked_at' => null,
                    'domain_error' => null,
                ]);
            }
        });

        // L'elenco host -> sito in cache si rifa' quando cambia un dominio o se si accende o spegne.
        static::saved(function (self $model) {
            if ($model->wasRecentlyCreated || $model->wasChanged([$model->domainColumn(), 'is_active'])) {
                HostDirectory::forget();
            }
        });

        static::deleted(fn () => HostDirectory::forget());

        // Eliminato, o con un indirizzo nuovo: il vecchio va tolto dal pannello dell'hosting (dalla coda).
        static::deleted(function (self $model) {
            static::removeFromHostingPanel($model->getRawOriginal($model->domainColumn()));
        });

        static::saved(function (self $model) {
            // wasRecentlyCreated resta vero per tutta la vita dell'oggetto: conta solo il valore di prima.
            $before = $model->getRawOriginal($model->domainColumn());

            if ($model->wasChanged($model->domainColumn()) && filled($before)
                && strcasecmp($before, (string) $model->{$model->domainColumn()}) !== 0) {
                static::removeFromHostingPanel($before);
            }
        });

        // Un dominio nuovo va sul pannello dell'hosting dalla coda, entro un minuto: la WHM
        // fa ripartire Apache e dentro la richiesta chiuderebbe la connessione del modulo.
        static::saved(function (self $model) {
            $host = $model->{$model->domainColumn()};

            if (blank($host) || app(HostingPanel::class) instanceof NoHostingPanel
                || ! ($model->wasRecentlyCreated || $model->wasChanged($model->domainColumn()))) {
                return;
            }

            RegisterDomainOnHostingPanel::dispatch(static::class, $model->getKey(), $host)->afterCommit();
        });
    }

    private static function removeFromHostingPanel(?string $host): void
    {
        if (blank($host) || app(HostingPanel::class) instanceof NoHostingPanel) {
            return;
        }

        RemoveDomainFromHostingPanel::dispatch($host)->afterCommit();
    }

    public function initializeHasDomainConnection(): void
    {
        $this->mergeCasts([
            'dns_verified_at' => 'datetime',
            'ssl_verified_at' => 'datetime',
            'domain_checked_at' => 'datetime',
        ]);
    }

    public function isConnected(): bool
    {
        return $this->dns_verified_at !== null && $this->ssl_verified_at !== null;
    }

    public function connectionLabel(): string
    {
        return match (true) {
            $this->domain_checked_at === null => 'Da verificare',
            $this->dns_verified_at === null => 'DNS da configurare',
            $this->ssl_verified_at === null => 'Certificato in attesa',
            default => 'Collegato',
        };
    }

    /** Per le colonne degli elenchi, che leggono attributi. */
    public function getConnectionStatusAttribute(): string
    {
        return $this->connectionLabel();
    }
}
