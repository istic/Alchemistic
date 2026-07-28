<?php

namespace App\Services\Oidc;

use Laravel\Passport\Passport;
use RuntimeException;

class OidcKey
{
    public static function publicKeyPem(): string
    {
        $path = Passport::keyPath('oauth-public.key');

        if (! is_readable($path)) {
            throw new RuntimeException("OIDC public key not found or unreadable at {$path}. Run `php artisan passport:keys`.");
        }

        return file_get_contents($path);
    }

    public static function kid(): string
    {
        return substr(hash('sha256', self::publicKeyPem()), 0, 16);
    }

    /**
     * @return array<string, string>
     */
    public static function jwk(): array
    {
        $publicKey = openssl_pkey_get_public(self::publicKeyPem());

        if ($publicKey === false) {
            throw new RuntimeException('OIDC public key at '.Passport::keyPath('oauth-public.key').' could not be parsed as a valid RSA key.');
        }

        $details = openssl_pkey_get_details($publicKey);

        if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('OIDC public key does not contain the expected RSA key details.');
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::kid(),
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];
    }
}
