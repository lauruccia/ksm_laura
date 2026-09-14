<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Quota KMoney scelta da un venditore per una categoria dei suoi prodotti. */
class KMoneyCategoryRule extends Model
{
    protected $table = 'kmoney_category_rules';

    protected $fillable = ['company_id', 'product_category_id', 'percent'];

    protected $casts = [
        'percent' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
