<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const TYPES = [
        'buyer' => 'Cliente',
        'vendor' => 'Azienda',
        'admin' => 'Amministrazione',
        'advertiser' => 'Inserzionista',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'profile_image',
        'user_type',
        'role_id',
        'is_active',
        'password',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_country',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Il ruolo di amministrazione, se ne ha uno. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function isVendor(): bool
    {
        return $this->user_type === 'vendor';
    }

    public function isBuyer(): bool
    {
        return $this->user_type === 'buyer';
    }

    /** Profilo inserzionista dell'esterno che compra campagne banner. */
    public function advertiser(): HasOne
    {
        return $this->hasOne(Advertiser::class);
    }

    public function isAdvertiser(): bool
    {
        return $this->user_type === 'advertiser';
    }

    public function hasActiveCompany(): bool
    {
        return (bool) $this->company?->is_active;
    }

    /**
     * L'indirizzo abituale, nella forma dei campi della cassa.
     *
     * @return array<string, string|null>
     */
    public function billingDefaults(): array
    {
        return [
            'billing_name' => $this->name,
            'billing_email' => $this->email,
            'billing_phone' => $this->phone,
            'billing_address' => $this->billing_address,
            'billing_city' => $this->billing_city,
            'billing_state' => $this->billing_state,
            'billing_zip' => $this->billing_zip,
            'billing_country' => $this->billing_country,
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->user_type] ?? (string) $this->user_type;
    }

    /**
     * Puo' fare questa cosa in amministrazione?
     *
     * Serve il tipo giusto, l'accesso non sospeso e un ruolo che la conceda.
     * Chi non ha ruolo non concede nulla: e' il caso di un collaboratore
     * appena creato a cui non e' ancora stato assegnato niente.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isAdmin() || ! $this->is_active) {
            return false;
        }

        return (bool) $this->role?->allows($permission);
    }
}
