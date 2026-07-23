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

    /**
     * Grant types this application actually supports (Authorization Code + PKCE, plus
     * the refresh tokens it issues). Passport's AuthorizationServer also wires up the
     * client_credentials grant unconditionally; this app has no use for it and no
     * client_secret distribution story for it, so it's rejected here explicitly.
     */
    private const SUPPORTED_GRANT_TYPES = ['authorization_code', 'refresh_token'];

    public function issueToken(ServerRequestInterface $psrRequest, PsrResponseInterface $psrResponse): Response
    {
        $grantType = $psrRequest->getParsedBody()['grant_type'] ?? null;

        if (! in_array($grantType, self::SUPPORTED_GRANT_TYPES, true)) {
            return response()->json([
                'error' => 'unsupported_grant_type',
                'error_description' => 'The authorization grant type is not supported by the authorization server.',
            ], 400);
        }

        $this->pendingIdToken->token = null;

        $response = parent::issueToken($psrRequest, $psrResponse);

        if ($response->getStatusCode() !== 200 || $this->pendingIdToken->token === null) {
            return $response;
        }

        $payload = json_decode($response->getContent(), true);
        $payload['id_token'] = $this->pendingIdToken->token;

        return response()->json($payload, $response->getStatusCode(), $response->headers->all());
    }
}
