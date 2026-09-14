<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chi compra campagne nel circuito banner.
 *
 * Un'azienda del sito vede le sue campagne nell'area azienda; un
 * inserzionista esterno ha un accesso suo, con `user_id`.
 */
class Advertiser extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'name', 'contact_name', 'email', 'phone', 'vat_number', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function advertisements(): HasMany
    {
        return $this->hasMany(Advertisement::class);
    }

    public function isCompany(): bool
    {
        return $this->company_id !== null;
    }

    public function kindLabel(): string
    {
        return $this->isCompany() ? 'Azienda del sito' : 'Esterno';
    }
}
