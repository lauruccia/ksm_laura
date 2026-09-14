<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un periodo di abbonamento di un'azienda a un piano.
 *
 * Nasce `pending` quando l'azienda sceglie il piano, diventa `active`
 * solo a incasso confermato: dal gestore per carta e PayPal,
 * dall'amministratore per il bonifico.
 */
class CompanySubscription extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PENDING => 'In attesa di pagamento',
        self::ACTIVE => 'Attivo',
        self::EXPIRED => 'Scaduto',
        self::CANCELLED => 'Annullato',
    ];

    protected $fillable = [
        'company_id', 'plan_id', 'replaces_id', 'status', 'payment_method',
        'price', 'credit', 'currency', 'starts_at', 'ends_at', 'cancelled_at',
        'notes', 'reminders_sent', 'send_reminders',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'credit' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminders_sent' => 'array',
        'send_reminders' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AdminTransaction::class, 'subscription_id');
    }

    /** Il periodo sostituito da questo, se e' nato da un cambio di piano. */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    /** Giorni che mancano alla scadenza; negativi se e' gia' passata. */
    public function daysLeft(): int
    {
        return $this->ends_at
            ? (int) ceil(now()->diffInDays($this->ends_at, absolute: false))
            : PHP_INT_MAX;
    }

    public function reminderSent(int $milestone): bool
    {
        return in_array($milestone, (array) $this->reminders_sent, true);
    }

    public function markReminderSent(int $milestone): void
    {
        $sent = (array) $this->reminders_sent;
        $sent[] = $milestone;

        $this->update(['reminders_sent' => array_values(array_unique($sent))]);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE
            && (! $this->ends_at || $this->ends_at->isFuture());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
