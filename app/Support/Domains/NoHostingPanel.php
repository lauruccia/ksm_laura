<?php

namespace App\Support\Domains;

/** Nessun pannello da avvisare: il VPS con Caddy, lo sviluppo, i test. */
class NoHostingPanel implements HostingPanel
{
    public function ensure(string $host): ?string
    {
        return null;
    }

    public function requestCertificate(): void
    {
    }
}
