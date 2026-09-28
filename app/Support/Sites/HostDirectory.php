<?php

namespace App\Support\Sites;

use App\Models\Company;
use App\Models\Domain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Quale sito risponde a un host: la piattaforma, un dominio della rete,
 * il dominio proprio di un'azienda, oppure nessuno.
 *
 * Con decine di domini cercare l'host in due tabelle a ogni richiesta
 * costa: l'elenco host -> sito sta in cache e si rifa' solo quando cambia
 * un dominio o un'azienda con dominio proprio (HasDomainConnection).
 * Per un host sconosciuto non si fa nessuna query.
 */
final class HostDirectory
{
    public const CACHE_KEY = 'sites:hosts:v1';

    private const TTL_SECONDS = 3600;

    /**
     * Host della piattaforma: quelli di KSM_PLATFORM_HOSTS piu' quello di
     * APP_URL, cosi' l'indirizzo con cui l'app e' configurata risponde
     * sempre, anche se manca dall'elenco.
     *
     * @return list<string>
     */
    public static function platformHosts(): array
    {
        $hosts = array_map(fn ($host) => self::normalise((string) $host), (array) config('ksm.platform_hosts'));

        if ($appHost = parse_url((string) config('app.url'), PHP_URL_HOST)) {
            $hosts[] = self::normalise($appHost);
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    public static function isPlatform(string $host): bool
    {
        return in_array(self::normalise($host), self::platformHosts(), true);
    }

    /**
     * Il sito di un host non di piattaforma.
     *
     * @return array{type: 'company'|'domain', id: int}|null
     */
    public static function find(string $host): ?array
    {
        return self::map()[self::normalise($host)] ?? null;
    }

    /** Vero se l'host e' di un sito: la piattaforma, un dominio o un'azienda. */
    public static function knows(string $host): bool
    {
        return self::isPlatform($host) || self::find($host) !== null;
    }

    /**
     * Da chiamare quando cambia un dominio o un'azienda con dominio proprio.
     *
     * A transazione chiusa: se la cache si svuotasse prima, una richiesta nel
     * frattempo la rifarebbe con i dati vecchi, e per un'ora il dominio
     * nuovo risponderebbe 404. Fuori da una transazione parte subito.
     */
    public static function forget(): void
    {
        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function normalise(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host);

        return preg_replace('/^www\./', '', $host);
    }

    /** @return array<string, array{type: 'company'|'domain', id: int}> */
    private static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, function () {
            $map = [];

            // Prima i domini della rete, poi le aziende: a parita' di host
            // vince l'azienda, come prima della cache.
            Domain::query()->where('is_active', true)->pluck('id', 'domain')
                ->each(function ($id, $host) use (&$map) {
                    $map[self::normalise((string) $host)] = ['type' => 'domain', 'id' => (int) $id];
                });

            Company::query()->where('is_active', true)->whereNotNull('custom_domain')->where('custom_domain', '!=', '')
                ->pluck('id', 'custom_domain')
                ->each(function ($id, $host) use (&$map) {
                    $map[self::normalise((string) $host)] = ['type' => 'company', 'id' => (int) $id];
                });

            return $map;
        });
    }
}
