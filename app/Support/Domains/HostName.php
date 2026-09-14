<?php

namespace App\Support\Domains;

/**
 * Nomi di dominio come li scrive chi compila un modulo, ridotti a uno solo.
 *
 * "https://www.DecinaBus.it/contatti" e "decinabus.it" sono lo stesso
 * dominio: si salva sempre la seconda forma, che e' quella cercata da
 * ResolveTenant.
 */
final class HostName
{
    public static function normalize(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $value);
        $value = preg_replace('#[/?\#].*$#', '', $value);
        $value = preg_replace('/:\d+$/', '', $value);

        return preg_replace('/^www\./', '', $value) ?: null;
    }

    public static function isValid(string $host): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host);
    }
}
