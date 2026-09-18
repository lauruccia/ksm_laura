<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'variant_type', 'variant_value', 'variant_price',
        'variant_stock', 'variant_sku', 'variant_images', 'attributes',
    ];

    protected $casts = ['variant_price' => 'decimal:2'];

    public function isInStock(): bool
    {
        return blank($this->variant_stock) || (int) $this->variant_stock > 0;
    }

    public function priceFor(Product $product): float
    {
        return $this->variant_price === null ? $product->final_price : (float) $this->variant_price;
    }

    public function label(): string
    {
        return implode(': ', array_filter([$this->variant_type, $this->variant_value], fn ($value) => filled($value)));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
