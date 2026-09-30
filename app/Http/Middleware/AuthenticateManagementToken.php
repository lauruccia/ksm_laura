<?php

namespace App\Http\Middleware;

use App\Models\ManagementApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateManagementToken
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $raw = $request->bearerToken();

        if (! is_string($raw) || ! str_starts_with($raw, 'ksm_')) {
            return $this->error($request, 401, 'unauthenticated', 'Token Bearer mancante o non valido.');
        }

        $token = ManagementApiToken::query()->with('company')->where('token_hash', hash('sha256', $raw))->first();

        if (! $token || $token->isExpired()) {
            return $this->error($request, 401, 'unauthenticated', 'Token Bearer mancante, non valido o scaduto.');
        }

        if (! $token->allows($scope)) {
            return $this->error($request, 403, 'insufficient_scope', "Lo scope {$scope} non e' autorizzato.");
        }

        $request->attributes->set('management_token', $token);
        $request->attributes->set('management_company', $token->company);
        $request->attributes->set('correlation_id', $request->header('X-Correlation-ID') ?: (string) Str::uuid());

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $request->attributes->get('correlation_id'));

        return $response;
    }

    private function error(Request $request, int $status, string $code, string $message): JsonResponse
    {
        $correlationId = $request->header('X-Correlation-ID') ?: (string) Str::uuid();

        return response()->json(['error' => [
            'code' => $code,
            'message' => $message,
            'correlation_id' => $correlationId,
        ]], $status, ['X-Correlation-ID' => $correlationId]);
    }
}
