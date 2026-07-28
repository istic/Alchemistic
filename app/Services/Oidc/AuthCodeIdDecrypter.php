<?php

namespace App\Services\Oidc;

use Illuminate\Contracts\Encryption\Encrypter;
use Laravel\Passport\Passport;
use League\OAuth2\Server\CryptTrait;
use Throwable;

/**
 * Decrypts a raw authorization code (as sent back to /oauth/token) using the
 * same encryption key League's AuthCodeGrant uses internally, so we can look
 * up data (the OIDC nonce) we stashed against the code's identifier while it
 * was still being issued.
 */
class AuthCodeIdDecrypter
{
    use CryptTrait;

    public function __construct(Encrypter $encrypter)
    {
        $this->setEncryptionKey(Passport::tokenEncryptionKey($encrypter));
    }

    public function decryptAuthCodeId(string $code): ?string
    {
        try {
            $payload = json_decode($this->decrypt($code));
        } catch (Throwable) {
            return null;
        }

        return $payload->auth_code_id ?? null;
    }
}
