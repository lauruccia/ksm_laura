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
        'stock' => 'integer',
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

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Pezzi venduti negli ordini pagati, in `sold_count`, per "i piu' venduti". */
    public function scopeWithSold($query)
    {
        return $query->withSum(['orderItems as sold_count' => fn ($q) => $q->whereHas(
            'order',
            fn ($order) => $order->whereIn('status', ['paid', 'shipped', 'completed'])
        )], 'quantity');
    }

    /** Peso da mostrare accanto al prezzo: "500 g", "1,5 kg". */
    public function getWeightLabelAttribute(): ?string
    {
        $kg = (float) $this->weight_kg;

        if ($kg <= 0) {
            return null;
        }

        return $kg < 1
            ? round($kg * 1000).' g'
            : rtrim(rtrim(number_format($kg, 2, ',', ''), '0'), ',').' kg';
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
        return $this->product_type === 'variable'
            ? $this->variants->contains(fn ($variant) => $variant->isInStock())
            : $this->stock === null || $this->stock > 0;
    }

    public function getDisplayPriceAttribute(): string
    {
        if ($this->product_type !== 'variable' || $this->variants->isEmpty()) {
            return \App\Support\Money::format($this->final_price);
        }
        $prices = $this->variants->map(fn ($variant) => $variant->priceFor($this));
        $min = $prices->min();
        $max = $prices->max();

        return \App\Support\Money::format($min).($min !== $max ? ' – '.\App\Support\Money::format($max) : '');
    }

    public function scopeAvailable($query)
    {
        return $query->where(function ($query) {
            $query->where(function ($query) {
                $query->where(fn ($q) => $q->where('product_type', '!=', 'variable')->orWhereNull('product_type'))
                    ->where(fn ($q) => $q->whereNull('stock')->orWhere('stock', '>', 0));
            })->orWhere(function ($query) {
                $query->where('product_type', 'variable')->whereHas('variants', fn ($q) => $q
                    ->whereNull('variant_stock')->orWhere('variant_stock', '')->orWhereRaw('CAST(variant_stock AS DECIMAL(12,0)) > 0'));
            });
        });
    }
}
