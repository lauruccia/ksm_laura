<?php

namespace App\Integrations\ManagementApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ManagementApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $company = $request->attributes->get('management_company');
        $token = $request->attributes->get('management_token');

        return response()->json(['data' => [
            'company_id' => $company->public_id,
            'name' => $company->name,
            'currency' => config('ksm.currency', 'EUR'),
            'timezone' => config('app.timezone', 'UTC'),
            'connector_version' => '1.0.0',
            'capabilities' => array_values(array_intersect($token->scopes, ManagementApiToken::SCOPES)),
        ]]);
    }
}
