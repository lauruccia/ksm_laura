<?php

namespace App\Support;

/**
 * Elenco chiuso di cio' che un ruolo di amministrazione puo' concedere.
 *
 * Le chiavi sono quelle salvate in `roles.permissions`; chi deve sapere se
 * una persona puo' fare qualcosa chiede al Gate, mai al nome del ruolo.
 * L'elenco lo decide il codice: un permesso tolto da qui sparisce ovunque.
 */
final class Permissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const COMPANIES_VIEW = 'companies.view';

    public const COMPANIES_MANAGE = 'companies.manage';

    public const SUBSCRIPTIONS_VIEW = 'subscriptions.view';

    public const SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';

    public const CATALOG_VIEW = 'catalog.view';

    public const CATALOG_MANAGE = 'catalog.manage';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_MANAGE = 'orders.manage';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_MANAGE = 'payments.manage';

    public const PLANS_MANAGE = 'plans.manage';

    public const CONTENT_MANAGE = 'content.manage';

    public const USERS_VIEW = 'users.view';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    /**
     * Permessi raggruppati per area, nella forma area => [chiave => etichetta].
     * I gruppi servono solo a mostrarli ordinati nel modulo del ruolo.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Riepilogo' => [
                self::DASHBOARD_VIEW => 'Vedere il riepilogo',
            ],
            'Aziende' => [
                self::COMPANIES_VIEW => 'Vedere le aziende',
                self::COMPANIES_MANAGE => 'Creare, modificare, sospendere aziende',
                self::SUBSCRIPTIONS_VIEW => 'Vedere gli abbonamenti',
                self::SUBSCRIPTIONS_MANAGE => 'Confermare incassi, attivare e annullare abbonamenti',
            ],
            'Catalogo' => [
                self::CATALOG_VIEW => 'Vedere prodotti, categorie e marche',
                self::CATALOG_MANAGE => 'Modificare prodotti, categorie e marche',
            ],
            'Vendite' => [
                self::ORDERS_VIEW => 'Vedere gli ordini',
                self::ORDERS_MANAGE => 'Cambiare stato ed eliminare ordini',
                self::PAYMENTS_VIEW => 'Vedere i pagamenti',
                self::PAYMENTS_MANAGE => 'Eliminare pagamenti',
                self::PLANS_MANAGE => 'Gestire i piani di abbonamento',
            ],
            'Contenuti' => [
                self::CONTENT_MANAGE => 'Pagine, banner e domini',
            ],
            'Sistema' => [
                self::USERS_VIEW => 'Vedere gli utenti',
                self::USERS_MANAGE => 'Creare e modificare utenti',
                self::ROLES_MANAGE => 'Gestire ruoli e permessi',
                self::SETTINGS_MANAGE => 'Impostazioni, pagamenti e posta in uscita',
            ],
        ];
    }

    /** @return array<string, string> chiave => etichetta, senza i gruppi */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key] ?? $key;
    }

    /** Scarta le chiavi che non esistono piu'. */
    public static function sanitize(array $keys): array
    {
        return array_values(array_intersect(self::keys(), array_map('strval', $keys)));
    }
}
