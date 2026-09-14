<?php

namespace App\Support\Domains;

/** Chi risponde alle domande sul DNS: il sistema in produzione, un finto nei test. */
interface DnsResolver
{
    /** @return list<string> indirizzi IPv4 e IPv6 a cui punta il dominio */
    public function addresses(string $host): array;

    /** Destinazione del record CNAME, se il dominio ne ha uno. */
    public function cname(string $host): ?string;
}
