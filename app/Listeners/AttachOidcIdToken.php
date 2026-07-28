<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Oidc\IdTokenBuilder;
use App\Services\Oidc\PendingIdToken;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Passport;

class AttachOidcIdToken
{
    public function __construct(
        private readonly IdTokenBuilder $idTokenBuilder,
        private readonly PendingIdToken $pendingIdToken,
    ) {}

    public function handle(AccessTokenCreated $event): void
    {
        if ($event->userId === null) {
            return;
        }

        $token = Passport::token()->find($event->tokenId);

        if ($token === null) {
            Log::warning('OIDC id_token attach skipped: access token not found immediately after creation.', [
                'token_id' => $event->tokenId,
                'client_id' => $event->clientId,
            ]);

            return;
        }

        if (! in_array('openid', $token->scopes, true)) {
            return;
        }

        $user = User::find($event->userId);

        if ($user === null) {
            Log::warning('OIDC id_token attach skipped: user not found for access token.', [
                'token_id' => $event->tokenId,
                'user_id' => $event->userId,
                'client_id' => $event->clientId,
            ]);

            return;
        }

        $this->pendingIdToken->token = $this->idTokenBuilder->build(
            $user,
            $event->clientId,
            $this->pendingIdToken->nonce,
        );
    }
}
