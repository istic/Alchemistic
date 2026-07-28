<?php

namespace App\Services\Oidc;

use Illuminate\Support\Facades\Cache;

class NonceStore
{
    /**
     * Auth codes live for 10 minutes (see PassportServiceProvider::buildAuthCodeGrant()),
     * so a nonce never needs to outlive that window.
     */
    private const TTL_MINUTES = 10;

    public static function put(string $authCodeId, string $nonce): void
    {
        Cache::put(self::key($authCodeId), $nonce, now()->addMinutes(self::TTL_MINUTES));
    }

    public static function pull(string $authCodeId): ?string
    {
        return Cache::pull(self::key($authCodeId));
    }

    private static function key(string $authCodeId): string
    {
        return "oidc-nonce:{$authCodeId}";
    }
}
