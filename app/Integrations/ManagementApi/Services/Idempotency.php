<?php

namespace App\Integrations\ManagementApi\Services;

use App\Models\Company;
use App\Models\ManagementApiIdempotencyKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Idempotency
{
    /**
     * @param  callable(): array{0: int, 1: array}  $operation
     */
    public function execute(
        Company $company,
        string $operationName,
        string $resourceId,
        string $key,
        array $payload,
        callable $operation,
    ): JsonResponse {
        $canonical = $this->canonical($payload);
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($company, $operationName, $resourceId, $key, $canonical, $hash, $operation): JsonResponse {
            Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            $existing = ManagementApiIdempotencyKey::query()
                ->where('company_id', $company->id)
                ->where('operation', $operationName)
                ->where('key', $key)
                ->first();

            if ($existing) {
                if ($existing->request_hash !== $hash || $existing->resource_id !== $resourceId) {
                    return response()->json(['error' => [
                        'code' => 'idempotency_conflict',
                        'message' => 'La chiave di idempotenza e gia associata a una richiesta diversa.',
                        'correlation_id' => request()->attributes->get('correlation_id'),
                    ]], 409);
                }

                return response()->json($existing->response_body, $existing->status_code, ['Idempotency-Replayed' => 'true']);
            }

            [$status, $body] = $operation();

            ManagementApiIdempotencyKey::query()->create([
                'company_id' => $company->id,
                'key' => $key,
                'operation' => $operationName,
                'resource_id' => $resourceId,
                'request_hash' => $hash,
                'request_body' => $canonical,
                'status_code' => $status,
                'response_body' => $body,
            ]);

            return response()->json($body, $status);
        }, 3);
    }

    private function canonical(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
