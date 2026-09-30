<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementApiIdempotencyKey extends Model
{
    protected $fillable = [
        'company_id', 'key', 'operation', 'resource_id', 'request_hash',
        'request_body', 'status_code', 'response_body',
    ];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
        'status_code' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
