<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserInfoController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->tokenCan('openid'), 403);

        return response()->json([
            'sub' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'permissions' => $user->permissions()->pluck('name')->values()->all(),
        ]);
    }
}
