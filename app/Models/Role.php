<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un ruolo di amministrazione: un nome e l'elenco di cio' che concede.
 *
 * Il ruolo di sistema `super-admin` concede tutto e non si puo' eliminare,
 * altrimenti basterebbe una spunta tolta per chiudersi fuori dal pannello.
 */
class Role extends Model
{
    public const SUPER_ADMIN = 'super-admin';

    protected $fillable = ['name', 'slug', 'description', 'permissions', 'is_system'];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN;
    }

    /** Il ruolo concede questo permesso? */
    public function allows(string $permission): bool
    {
        return $this->isSuperAdmin()
            || in_array($permission, (array) $this->permissions, true);
    }

    /** Etichette dei permessi concessi, per mostrarli senza ripeterli a mano. */
    public function permissionLabels(): array
    {
        if ($this->isSuperAdmin()) {
            return ['Accesso completo'];
        }

        return array_map(
            fn ($key) => Permissions::label($key),
            Permissions::sanitize((array) $this->permissions)
        );
    }

    public function permissionCount(): int
    {
        return $this->isSuperAdmin()
            ? count(Permissions::keys())
            : count(Permissions::sanitize((array) $this->permissions));
    }
}
