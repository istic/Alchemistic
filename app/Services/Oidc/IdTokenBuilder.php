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
use RuntimeException;

class IdTokenBuilder
{
    public function build(User $user, string $clientId, ?string $nonce = null): string
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

        if ($nonce !== null) {
            $builder = $builder->withClaim('nonce', $nonce);
        }

        return $builder
            ->getToken(new Sha256, InMemory::plainText($this->privateKeyPem()))
            ->toString();
    }

    private function privateKeyPem(): string
    {
        $path = Passport::keyPath('oauth-private.key');

        if (! is_readable($path)) {
            throw new RuntimeException("OIDC private key not found or unreadable at {$path}. Run `php artisan passport:keys`.");
        }

        return file_get_contents($path);
    }
}
