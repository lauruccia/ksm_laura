<?php

namespace App\Integrations\ManagementApi\Services;

use App\Models\Company;
use App\Models\ManagementWebhookOutbox;
use Illuminate\Support\Str;

class WebhookOutbox
{
    public function record(Company $company, string $event, string $resourceType, string $resourceId, int $version): void
    {
        $occurredAt = now()->utc();

        $eventId = (string) Str::uuid();
        $body = json_encode([
            'event_id' => $eventId,
            'event' => $event,
            'occurred_at' => $occurredAt->toISOString(),
            'company_id' => $company->public_id,
            'data' => [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'version' => $version,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        ManagementWebhookOutbox::query()->create([
            'event_id' => $eventId,
            'company_id' => $company->id,
            'event_type' => $event,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'body' => $body,
            'occurred_at' => $occurredAt,
            'next_attempt_at' => now(),
        ]);
    }
}
