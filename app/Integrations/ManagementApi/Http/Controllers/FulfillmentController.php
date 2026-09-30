<?php

namespace App\Integrations\ManagementApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\ManagementApi\Services\Idempotency;
use App\Integrations\ManagementApi\Services\WebhookOutbox;
use App\Models\Order;
use App\Support\Orders\OrderStock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FulfillmentController extends Controller
{
    public function __invoke(
        Request $request,
        string $id,
        Idempotency $idempotency,
        OrderStock $stock,
        WebhookOutbox $outbox,
    ): JsonResponse {
        $key = (string) $request->header('Idempotency-Key');
        $validator = Validator::make($request->all(), [
            'expected_version' => ['required', 'integer', 'min:1'],
            'carrier' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:150'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:500'],
            'shipped_at' => ['required', 'date'],
        ]);

        if (! Str::isUuid($key)) {
            $validator->errors()->add('Idempotency-Key', 'Deve essere un UUID valido.');
        }

        if ($validator->fails()) {
            return response()->json($this->error('validation_failed', 'I dati inviati non sono validi.', [
                'details' => $validator->errors()->toArray(),
            ]), 422);
        }

        $company = $request->attributes->get('management_company');
        $payload = $validator->validated();

        return $idempotency->execute($company, 'fulfillment.create', $id, $key, $payload, function () use ($company, $id, $payload, $stock, $outbox): array {
            $order = Order::query()->where('company_id', $company->id)->where('public_id', $id)->lockForUpdate()->first();

            if (! $order) {
                return [404, $this->error('not_found', 'Ordine non trovato.')];
            }

            if ((int) $order->integration_version !== (int) $payload['expected_version']) {
                return [409, $this->error('version_conflict', 'La risorsa e stata modificata.', [
                    'current_version' => (int) $order->integration_version,
                ])];
            }

            if ($order->status === 'cancelled') {
                return [409, $this->error('invalid_transition', 'Un ordine annullato non puo essere spedito.')];
            }

            $order->forceFill([
                'carrier' => $payload['carrier'] ?? null,
                'tracking_number' => $payload['tracking_number'] ?? null,
                'tracking_url' => $payload['tracking_url'] ?? null,
                'shipped_at' => CarbonImmutable::parse($payload['shipped_at'])->utc(),
                'status' => $order->status === 'completed' ? 'completed' : 'shipped',
            ])->save();
            $stock->sync($order);
            $order->refresh();
            $outbox->record($company, 'fulfillment.updated', 'order', $order->public_id, (int) $order->integration_version);

            return [201, ['data' => [
                'order_id' => $order->public_id,
                'commercial_status' => $order->status,
                'carrier' => $order->carrier,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
                'shipped_at' => $order->shipped_at->utc()->toISOString(),
                'version' => (int) $order->integration_version,
                'updated_at' => $order->updated_at->utc()->toISOString(),
            ]]];
        });
    }

    private function error(string $code, string $message, array $extra = []): array
    {
        return ['error' => array_merge([
            'code' => $code,
            'message' => $message,
            'correlation_id' => request()->attributes->get('correlation_id'),
        ], $extra)];
    }
}
