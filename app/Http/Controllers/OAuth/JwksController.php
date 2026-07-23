<?php

namespace App\Http\Controllers\OAuth;

use App\Services\Oidc\OidcKey;
use Illuminate\Http\JsonResponse;

class JwksController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'keys' => [OidcKey::jwk()],
        ]);
    }
}
