<?php

namespace App\Services\Oidc;

use Illuminate\Http\Request;
use Laravel\Passport\Bridge\AuthCodeRepository as PassportAuthCodeRepository;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;

/**
 * Stashes the `nonce` query parameter from the /oauth/authorize request
 * against the newly-minted auth code's identifier, so it can be retrieved
 * and echoed back into the id_token once the code is redeemed at /oauth/token
 * (see AuthCodeIdDecrypter and NonceStore).
 */
class AuthCodeRepository extends PassportAuthCodeRepository
{
    public function __construct(private readonly Request $request) {}

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        parent::persistNewAuthCode($authCodeEntity);

        $nonce = $this->request->query('nonce');

        if (is_string($nonce) && $nonce !== '') {
            NonceStore::put($authCodeEntity->getIdentifier(), $nonce);
        }
    }
}
