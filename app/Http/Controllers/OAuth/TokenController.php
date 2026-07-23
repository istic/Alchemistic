<?php

namespace App\Http\Controllers\OAuth;

use App\Services\Oidc\PendingIdToken;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

class TokenController extends AccessTokenController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly PendingIdToken $pendingIdToken,
    ) {
        parent::__construct($server);
    }

    public function issueToken(ServerRequestInterface $psrRequest, PsrResponseInterface $psrResponse): Response
    {
        $response = parent::issueToken($psrRequest, $psrResponse);

        if ($response->getStatusCode() !== 200 || $this->pendingIdToken->token === null) {
            return $response;
        }

        $payload = json_decode($response->getContent(), true);
        $payload['id_token'] = $this->pendingIdToken->token;

        return response()->json($payload, $response->getStatusCode(), $response->headers->all());
    }
}
