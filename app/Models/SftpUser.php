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

    public function setPasswordAttribute(string $value): void
    {
        // Store as SHA512 crypt hash (compatible with /etc/shadow and atmoz/sftp)
        if (! str_starts_with($value, '$6$')) {
            $salt = '$6$'.substr(str_replace('+', '.', base64_encode(random_bytes(12))), 0, 16).'$';
            $value = crypt($value, $salt);
        }

        $this->attributes['password'] = $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
