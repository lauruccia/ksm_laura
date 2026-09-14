<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use Concerns\HasDomainConnection;

    /** Colonna con il nome del dominio, per lo stato di collegamento. */
    public function domainColumn(): string
    {
        return 'domain';
    }

    /** home | category | city | category+city | company */
    public const TYPES = ['home', 'category', 'city', 'category+city', 'company'];

    protected $fillable = [
        'name', 'domain', 'type', 'company_category_id', 'product_category_id',
        'logo', 'city', 'address', 'phone', 'email', 'social_links',
        'description', 'is_active',
        'header_variant', 'header_background', 'header_color', 'header_accent',
        'header_tagline', 'header_subline',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_active' => 'boolean',
    ];

    public function companyCategory(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function filtersByCategory(): bool
    {
        return in_array($this->type, ['category', 'category+city'], true);
    }

    public function filtersByCity(): bool
    {
        return in_array($this->type, ['city', 'category+city'], true);
    }
}
