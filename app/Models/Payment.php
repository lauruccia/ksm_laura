<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'method', 'mode', 'amount',
        'currency', 'transaction_id', 'status', 'response',
    ];

    protected $casts = [
        'response' => 'array',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** L'ordine di cui questo pagamento e' la parte in euro. */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /** L'ordine di cui questo pagamento e' la quota KMoney. */
    public function kmoneyOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'kmoney_payment_id');
    }

    /** L'ordine a cui appartiene, qualunque parte sia. */
    public function belongingOrder(): ?Order
    {
        return $this->order ?? $this->kmoneyOrder;
    }

    public function isKmoney(): bool
    {
        return $this->method === 'kmoney';
    }
}
