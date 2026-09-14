<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Traduce i permessi del ruolo in regole del framework.
 *
 * Da qui in poi si chiede sempre `can('utenti.gestire')` e mai
 * "che ruolo ha": aggiungere un ruolo non tocca nessuna pagina.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Chi ha il ruolo di sistema passa senza controllare la singola voce.
        Gate::before(function (User $user, string $ability) {
            if (! in_array($ability, Permissions::keys(), true)) {
                return null;
            }

            if (! $user->isAdmin() || ! $user->is_active) {
                return false;
            }

            return $user->role?->slug === Role::SUPER_ADMIN ? true : null;
        });

        foreach (Permissions::keys() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
