<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManagementWebhookOutbox extends Model
{
    protected $table = 'management_webhook_outbox';

    protected $fillable = [
        'event_id', 'company_id', 'event_type', 'resource_type',
        'resource_id', 'body', 'occurred_at', 'attempts', 'next_attempt_at',
        'delivered_at', 'last_error',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
