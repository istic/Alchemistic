<?php

namespace App\Services\Oidc;

use Laravel\Passport\Passport;

class OidcKey
{
    public static function publicKeyPem(): string
    {
        return file_get_contents(Passport::keyPath('oauth-public.key'));
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
        $details = openssl_pkey_get_details(openssl_pkey_get_public(self::publicKeyPem()));

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
