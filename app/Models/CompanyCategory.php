<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCategory extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id', 'description', 'icon'];

    /** Una categoria cambiata rende vecchio l'albero gia' letto in questa richiesta. */
    protected static function booted(): void
    {
        $forget = fn () => \App\Support\CategoryTree::forget(static::class);
        static::saved($forget);
        static::deleted($forget);
    }

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
