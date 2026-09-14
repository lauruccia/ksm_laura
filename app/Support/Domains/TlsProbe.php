<?php

namespace App\Support\Domains;

/** Prova il certificato di un dominio: una connessione vera in produzione, una finta nei test. */
interface TlsProbe
{
    /** Null se il certificato e' valido per il dominio, altrimenti il motivo. */
    public function check(string $host): ?string;
}
