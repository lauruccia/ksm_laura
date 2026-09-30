<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Server e pannello dell'hosting scelti in Amministrazione (una riga sola).
 *
 * Con "env" vale il .env com'e'. Con un'altra scelta i valori di qui
 * prendono il posto di ksm.server, ksm.cpanel e ksm.whm: per traslocare
 * su un altro cPanel, un'altra WHM o un VPS basta cambiarli qui e poi
 * "Ricollega" i domini. I token si salvano cifrati.
 */
class HostingSetting extends Model
{
    public const PANELS = [
        'env' => 'Come nel file .env del server',
        'whm' => 'WHM di un rivenditore (un account per dominio)',
        'cpanel' => 'Un account cPanel (domini aggiuntivi o alias)',
        'none' => 'Nessun pannello (VPS con Caddy, o domini aggiunti a mano)',
    ];

    private const CACHE_KEY = 'hosting-settings';

    protected $fillable = [
        'panel', 'server_ips', 'server_cname',
        'cpanel_url', 'cpanel_user', 'cpanel_token', 'cpanel_docroot',
        'whm_url', 'whm_reseller', 'whm_token', 'whm_account',
        'whm_proxy_plan', 'whm_proxy_target', 'whm_contact_email',
    ];

    protected $casts = [
        'cpanel_token' => 'encrypted',
        'whm_token' => 'encrypted',
    ];

    protected $hidden = ['cpanel_token', 'whm_token'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['panel' => 'env']);
    }

    /**
     * Mette le scelte dell'Amministrazione al posto del .env. Si chiama prima
     * di costruire il pannello: senza tabella (prima della migrazione) o con
     * "env" non cambia niente.
     */
    public static function applyToConfig(): void
    {
        $values = rescue(fn () => Cache::rememberForever(self::CACHE_KEY, function () {
            $row = static::query()->first();

            return $row ? $row->only($row->getFillable()) : null;
        }), null, false);

        if (! $values || ($values['panel'] ?? 'env') === 'env') {
            return;
        }

        $list = fn (?string $value) => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

        config([
            'ksm.server.ips' => $list($values['server_ips'] ?? null),
            'ksm.server.cname' => $values['server_cname'] ?: null,
            'ksm.cpanel' => $values['panel'] === 'cpanel' ? [
                'url' => $values['cpanel_url'], 'user' => $values['cpanel_user'], 'token' => $values['cpanel_token'],
                'docroot' => $values['cpanel_docroot'] ?: 'ksm-next/public',
            ] : ['url' => null, 'user' => null, 'token' => null, 'docroot' => 'ksm-next/public'],
            'ksm.whm' => $values['panel'] === 'whm' ? [
                'url' => $values['whm_url'], 'reseller' => $values['whm_reseller'], 'token' => $values['whm_token'],
                'account' => $values['whm_account'], 'proxy_plan' => $values['whm_proxy_plan'] ?: null,
                'proxy_target' => $values['whm_proxy_target'] ?: config('app.url'),
                'contact_email' => $values['whm_contact_email'] ?: null,
            ] : ['url' => null, 'reseller' => null, 'token' => null, 'account' => null],
        ]);
    }
}
