<?php

namespace App\Integrations\ManagementApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\ManagementApi\Services\Idempotency;
use App\Integrations\ManagementApi\Services\WebhookOutbox;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function __invoke(Request $request, string $id, Idempotency $idempotency, WebhookOutbox $outbox): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        $validator = Validator::make($request->all(), [
            'available_quantity' => ['required', 'integer', 'min:0'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! Str::isUuid($key)) {
            $validator->errors()->add('Idempotency-Key', 'Deve essere un UUID valido.');
        }

        if ($validator->fails()) {
            return $this->unprocessable($request, $validator->errors()->toArray());
        }

        $company = $request->attributes->get('management_company');
        $payload = $validator->validated();

        return $idempotency->execute($company, 'inventory.replace', $id, $key, $payload, function () use ($company, $id, $payload, $outbox): array {
            $resource = Product::query()->where('company_id', $company->id)->where('public_id', $id)->lockForUpdate()->first();
            $type = 'product';

            if (! $resource) {
                $resource = ProductVariant::query()->whereHas('product', fn ($query) => $query->where('company_id', $company->id))
                    ->where('public_id', $id)->lockForUpdate()->first();
                $type = 'variant';
            }

            if (! $resource) {
                return [404, $this->error('not_found', 'Risorsa inventario non trovata.')];
            }

            if ((int) $resource->integration_version !== (int) $payload['expected_version']) {
                return [409, $this->error('version_conflict', 'La risorsa e stata modificata.', [
                    'current_version' => (int) $resource->integration_version,
                ])];
            }

            if ($resource instanceof ProductVariant) {
                $resource->variant_stock = (string) $payload['available_quantity'];
            } else {
                $resource->stock = $payload['available_quantity'];
            }

            $resource->save();
            $resource->refresh();
            $outbox->record($company, 'inventory.updated', $type, $resource->public_id, (int) $resource->integration_version);

            return [200, ['data' => [
                'id' => $resource->public_id,
                'resource_type' => $type,
                'stock_managed' => true,
                'available_quantity' => (int) $payload['available_quantity'],
                'version' => (int) $resource->integration_version,
                'updated_at' => $resource->updated_at->utc()->toISOString(),
            ]]];
        });
    }

    private function unprocessable(Request $request, array $details): JsonResponse
    {
        return response()->json($this->error('validation_failed', 'I dati inviati non sono validi.', ['details' => $details]), 422);
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
