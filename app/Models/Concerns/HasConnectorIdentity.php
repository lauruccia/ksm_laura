<?php

namespace App\Models\Concerns;

use App\Integrations\ManagementApi\Services\WebhookOutbox;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

trait HasConnectorIdentity
{
    protected static function bootHasConnectorIdentity(): void
    {
        static::creating(function ($model): void {
            $model->public_id ??= (string) Str::uuid();
            $model->integration_version ??= 1;
        });

        static::updating(function ($model): void {
            if (! $model->isDirty('integration_version')) {
                $model->integration_version = max(1, (int) $model->getOriginal('integration_version')) + 1;
            }
        });

        static::created(fn ($model) => static::recordConnectorEvent($model, true));
        static::updated(fn ($model) => static::recordConnectorEvent($model, false));
    }

    private static function recordConnectorEvent($model, bool $created): void
    {
        [$event, $type, $company] = match (true) {
            $model instanceof Product => ['product.updated', 'product', $model->company],
            $model instanceof ProductVariant => ['product.updated', 'variant', $model->product?->company],
            $model instanceof Order => [$created ? 'order.created' : 'order.updated', 'order', $model->company],
            default => [null, null, null],
        };

        if ($event && $company && $model->public_id) {
            app(WebhookOutbox::class)->record(
                $company,
                $event,
                $type,
                $model->public_id,
                (int) $model->integration_version,
            );
        }
    }
}
