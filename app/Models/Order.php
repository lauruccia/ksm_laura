<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Order extends Model
{
    public const STATUSES = ['pending', 'paid', 'shipped', 'completed', 'cancelled'];

    /** Gli stati come si leggono nei pannelli. */
    public const STATUS_LABELS = [
        'pending' => 'In attesa',
        'paid' => 'Pagato',
        'shipped' => 'Spedito',
        'completed' => 'Concluso',
        'cancelled' => 'Annullato',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    protected $fillable = [
        'user_id', 'company_id', 'payment_id', 'kmoney_payment_id', 'subtotal', 'shipping', 'tax', 'total',
        'kmoney_total', 'currency', 'status', 'billing_name', 'billing_email', 'billing_phone',
        'billing_address', 'billing_city', 'billing_state', 'billing_zip',
        'billing_country', 'shipping_address', 'notes',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'kmoney_total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** La parte in euro. */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** La quota KMoney, se l'ordine ne ha una. */
    public function kmoneyPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'kmoney_payment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * I pagamenti che l'ordine richiede, nell'ordine in cui si fanno:
     * prima la quota KMoney, poi la parte in euro.
     *
     * @return Collection<int, Payment>
     */
    public function requiredPayments(): Collection
    {
        return collect([$this->kmoneyPayment, $this->payment])->filter()->values();
    }

    /** Il primo pagamento non ancora incassato, se ne manca uno. */
    public function pendingPayment(): ?Payment
    {
        return $this->requiredPayments()->first(fn (Payment $payment) => $payment->status !== 'completed');
    }

    public function isFullyPaid(): bool
    {
        return $this->requiredPayments()->isNotEmpty() && $this->pendingPayment() === null;
    }

    public function hasKmoney(): bool
    {
        return (float) $this->kmoney_total > 0;
    }

    /** Parte del totale che si paga in euro. */
    public function getEuroTotalAttribute(): float
    {
        return round((float) $this->total - (float) $this->kmoney_total, 2);
    }

    public function getReferenceAttribute(): string
    {
        return 'KSM-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
