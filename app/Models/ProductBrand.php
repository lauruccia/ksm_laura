<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBrand extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'icon'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }
}
