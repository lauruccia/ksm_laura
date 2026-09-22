<?php

namespace App\Models\Concerns;

use App\Support\Domains\DomainConnectionChecker;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\NoHostingPanel;

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

        // Un dominio nuovo va subito sul pannello dell'hosting, dopo la risposta per non far aspettare il modulo.
        static::saved(function (self $model) {
            $host = $model->{$model->domainColumn()};

            if (blank($host) || app(HostingPanel::class) instanceof NoHostingPanel
                || ! ($model->wasRecentlyCreated || $model->wasChanged($model->domainColumn()))) {
                return;
            }

            dispatch(function () use ($model, $host) {
                app(DomainConnectionChecker::class)->refresh($model, $host);
            })->afterResponse();
        });
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
