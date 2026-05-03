<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SftpUser extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'password',
        'public_key',
        'uid',
        'gid',
    ];

    protected $hidden = ['password'];

    protected static function booted(): void
    {
        static::creating(function (SftpUser $sftpUser) {
            if (empty($sftpUser->uid)) {
                $sftpUser->uid = (static::max('uid') ?? 1999) + 1;
            }
        });
    }

    public function setPasswordAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['password'] = null;

            return;
        }

        // Store as SHA512 crypt hash (compatible with /etc/shadow and atmoz/sftp)
        if (! str_starts_with($value, '$6$')) {
            $salt = '$6$'.substr(str_replace('+', '.', base64_encode(random_bytes(12))), 0, 16).'$';
            $value = crypt($value, $salt);
        }

        $this->attributes['password'] = $value;
    }

    public function getPublicKeyFingerprintAttribute(): ?string
    {
        if (! $this->public_key) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($this->public_key), 3);

        if (count($parts) < 2) {
            return null;
        }

        $decoded = base64_decode($parts[1], strict: true);

        if ($decoded === false) {
            return null;
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $decoded, true)), '=');
    }

    public function getPublicKeyTypeAttribute(): ?string
    {
        if (! $this->public_key) {
            return null;
        }

        return preg_split('/\s+/', trim($this->public_key), 2)[0] ?? null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
