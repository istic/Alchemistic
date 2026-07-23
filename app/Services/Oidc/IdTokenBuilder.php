<?php

namespace App\Services\Oidc;

use App\Models\User;
use DateTimeImmutable;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;

class IdTokenBuilder
{
    public function build(User $user, string $clientId): string
    {
        $now = new DateTimeImmutable;

        $builder = (new Builder(new JoseEncoder, ChainedFormatter::default()))
            ->issuedBy(config('app.url'))
            ->permittedFor($clientId)
            ->relatedTo((string) $user->getKey())
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withHeader('kid', OidcKey::kid())
            ->withClaim('name', $user->name)
            ->withClaim('email', $user->email)
            ->withClaim('email_verified', $user->email_verified_at !== null)
            ->withClaim('permissions', $user->permissions()->pluck('name')->values()->all());

        return $builder
            ->getToken(new Sha256, InMemory::plainText(
                file_get_contents(Passport::keyPath('oauth-private.key'))
            ))
            ->toString();
    }
}
