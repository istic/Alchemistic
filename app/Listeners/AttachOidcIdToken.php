<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Oidc\IdTokenBuilder;
use App\Services\Oidc\PendingIdToken;
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

        if ($token === null || ! in_array('openid', $token->scopes, true)) {
            return;
        }

        $user = User::find($event->userId);

        if ($user === null) {
            return;
        }

        $this->pendingIdToken->token = $this->idTokenBuilder->build($user, $event->clientId);
    }
}
