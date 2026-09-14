<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['company_id', 'name', 'email', 'rating', 'comment', 'ip', 'user_agent'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
