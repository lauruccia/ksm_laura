<?php

namespace App\Support\Domains;

final class SystemDnsResolver implements DnsResolver
{
    public function addresses(string $host): array
    {
        // Un dominio inesistente fa emettere un warning a dns_get_record: qui vale "nessun record".
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records
        )));
    }

    public function cname(string $host): ?string
    {
        $records = @dns_get_record($host, DNS_CNAME) ?: [];

        return $records[0]['target'] ?? null;
    }
}
