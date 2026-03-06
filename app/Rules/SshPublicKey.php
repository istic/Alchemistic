<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SshPublicKey implements ValidationRule
{
    private const KEY_TYPES = [
        'ssh-rsa',
        'ssh-ed25519',
        'ssh-dss',
        'ecdsa-sha2-nistp256',
        'ecdsa-sha2-nistp384',
        'ecdsa-sha2-nistp521',
        'sk-ssh-ed25519@openssh.com',
        'sk-ecdsa-sha2-nistp256@openssh.com',
    ];

    private const PRIVATE_KEY_MARKERS = [
        '-----BEGIN OPENSSH PRIVATE KEY-----',
        '-----BEGIN RSA PRIVATE KEY-----',
        '-----BEGIN EC PRIVATE KEY-----',
        '-----BEGIN DSA PRIVATE KEY-----',
        '-----BEGIN PRIVATE KEY-----',
        '-----BEGIN ENCRYPTED PRIVATE KEY-----',
    ];

    private const PEM_PUBLIC_MARKERS = [
        '-----BEGIN PUBLIC KEY-----',
        '-----BEGIN RSA PUBLIC KEY-----',
    ];

    /**
     * Convert a PEM public key or OpenSSH public key to OpenSSH authorized_keys format.
     * Returns the input unchanged if it's already in OpenSSH format.
     *
     * @throws \InvalidArgumentException if the key cannot be converted
     */
    public static function normalize(string $value): string
    {
        $trimmed = trim($value);

        foreach (self::PEM_PUBLIC_MARKERS as $marker) {
            if (str_contains($trimmed, $marker)) {
                return self::convertPemToOpenSsh($trimmed);
            }
        }

        return $trimmed;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $trimmed = trim($value);

        foreach (self::PRIVATE_KEY_MARKERS as $marker) {
            if (str_contains($trimmed, $marker)) {
                $fail('The :attribute must be a public key, not a private key.');
                return;
            }
        }

        // Accept PEM public keys — normalize() will convert them before saving
        foreach (self::PEM_PUBLIC_MARKERS as $marker) {
            if (str_contains($trimmed, $marker)) {
                try {
                    self::convertPemToOpenSsh($trimmed);
                } catch (\InvalidArgumentException $e) {
                    $fail($e->getMessage());
                }
                return;
            }
        }

        $parts = preg_split('/\s+/', $trimmed, 3);

        if (count($parts) < 2) {
            $fail('The :attribute must be a valid SSH public key.');
            return;
        }

        [$type, $keyData] = $parts;

        if (! in_array($type, self::KEY_TYPES, true)) {
            $fail('The :attribute has an unsupported key type.');
            return;
        }

        $decoded = base64_decode($keyData, strict: true);

        if ($decoded === false) {
            $fail('The :attribute key data is not valid base64.');
            return;
        }

        if (strlen($decoded) < 4) {
            $fail('The :attribute is not a valid SSH public key.');
            return;
        }

        $len = unpack('N', substr($decoded, 0, 4))[1];

        if (strlen($decoded) < 4 + $len) {
            $fail('The :attribute is not a valid SSH public key.');
            return;
        }

        $encodedType = substr($decoded, 4, $len);

        if (! str_starts_with($type, $encodedType) && ! str_starts_with($encodedType, explode('@', $type)[0])) {
            $fail('The :attribute key type does not match the key data.');
        }
    }

    private static function convertPemToOpenSsh(string $pem): string
    {
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw new \InvalidArgumentException('Invalid PEM public key.');
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            throw new \InvalidArgumentException('Could not read PEM public key details.');
        }

        return match ($details['type']) {
            OPENSSL_KEYTYPE_RSA => self::encodeRsaOpenSsh($details['rsa']),
            default => throw new \InvalidArgumentException(
                'PEM conversion only supports RSA keys. For EC or Ed25519 keys, paste the OpenSSH public key (ssh-keygen -y).'
            ),
        };
    }

    private static function encodeRsaOpenSsh(array $rsa): string
    {
        $data = self::sshString('ssh-rsa')
            . self::sshMpint($rsa['e'])
            . self::sshMpint($rsa['n']);

        return 'ssh-rsa ' . base64_encode($data);
    }

    private static function sshString(string $s): string
    {
        return pack('N', strlen($s)) . $s;
    }

    private static function sshMpint(string $bytes): string
    {
        // Prepend zero byte if high bit is set (two's complement positive)
        if (strlen($bytes) > 0 && (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        }

        return pack('N', strlen($bytes)) . $bytes;
    }
}
