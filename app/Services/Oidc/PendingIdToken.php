<?php

namespace App\Services\Oidc;

class PendingIdToken
{
    public ?string $token = null;

    public ?string $nonce = null;
}
