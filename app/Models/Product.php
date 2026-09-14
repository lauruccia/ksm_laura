<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'company_id', 'category_id', 'brand_id', 'name', 'slug', 'sku',
        'short_description', 'description', 'price', 'discount_price',
        'kmoney_discount_percent', 'kmoney_percent', 'stock', 'weight_kg', 'fixed_shipping_cost',
        'featured_image', 'gallery_images', 'product_type', 'status',
    ];

    protected $casts = [
        'gallery_images' => 'array',
        // Quota KMoney effettiva, ricalcolata da KMoneyPercentages.
        'kmoney_percent' => 'integer',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Prezzo effettivo di vendita, sconto incluso. */
    public function getFinalPriceAttribute(): float
    {
        $discount = (float) $this->discount_price;

        return $discount > 0 && $discount < (float) $this->price
            ? $discount
            : (float) $this->price;
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }
}
