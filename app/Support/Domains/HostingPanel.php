<?php

namespace App\Support\Domains;

/**
 * Il pannello dell'hosting che deve conoscere i domini dell'app.
 *
 * Su cPanel un dominio che il pannello non conosce non arriva all'app e
 * non riceve il certificato; sul VPS ci pensa Caddy e il pannello non c'e'.
 */
interface HostingPanel
{
    /** Aggiunge il dominio se manca. Null se c'e', altrimenti il motivo. */
    public function ensure(string $host): ?string;

    /** Toglie il dominio dal pannello, se e' li'. Null se fatto o se non c'era, altrimenti il motivo. */
    public function remove(string $host): ?string;

    /** Chiede subito il certificato, senza aspettare il giro notturno di AutoSSL. */
    public function requestCertificate(): void;
}
