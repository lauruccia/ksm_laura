<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCategory extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id', 'description', 'icon'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'category_id');
    }
}
