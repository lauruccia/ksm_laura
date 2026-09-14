<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTransaction extends Model
{
    protected $fillable = [
        'company_id', 'plan_id', 'subscription_id', 'payment_method', 'mode',
        'transaction_id', 'amount', 'currency', 'status', 'response_data',
    ];

    protected $casts = [
        'response_data' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CompanySubscription::class, 'subscription_id');
    }
}
