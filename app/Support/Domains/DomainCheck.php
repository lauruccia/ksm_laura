<?php

namespace App\Support\Domains;

/** Esito di una verifica: DNS, certificato e, se qualcosa manca, il perche'. */
final class DomainCheck
{
    public function __construct(
        public readonly bool $dns,
        public readonly bool $ssl,
        public readonly ?string $error = null,
    ) {
    }

    public function connected(): bool
    {
        return $this->dns && $this->ssl;
    }
}
