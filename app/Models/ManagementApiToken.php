<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ManagementApiToken extends Model
{
    public const READ_SCOPES = ['identity:read', 'catalog:read', 'orders:read'];

    public const WRITE_SCOPES = ['inventory:write', 'fulfillments:write'];

    public const SCOPES = [...self::READ_SCOPES, ...self::WRITE_SCOPES];

    protected $fillable = [
        'company_id', 'created_by', 'name', 'token_hash', 'token_prefix',
        'scopes', 'expires_at', 'last_used_at', 'last_used_ip',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return array{0: self, 1: string} */
    public static function issue(Company $company, string $name, array $scopes, ?int $days = 90): array
    {
        $raw = 'ksm_'.Str::random(64);
        $token = static::query()->create([
            'company_id' => $company->getKey(),
            'name' => $name,
            'token_hash' => hash('sha256', $raw),
            'token_prefix' => substr($raw, 0, 12),
            'scopes' => array_values(array_unique($scopes)),
            'expires_at' => $days === null ? null : now()->addDays($days),
        ]);

        return [$token, $raw];
    }
}
