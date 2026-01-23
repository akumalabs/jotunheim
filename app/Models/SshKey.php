<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SshKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'public_key',
        'fingerprint',
    ];

    /**
     * Get the user who owns this SSH key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate fingerprint from public key before saving.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SshKey $key) {
            if (empty($key->fingerprint)) {
                $key->fingerprint = self::generateFingerprint($key->public_key);
            }
        });
    }

    /**
     * Generate SSH key fingerprint.
     */
    public static function generateFingerprint(string $publicKey): string
    {
        $parts = explode(' ', trim($publicKey));
        if (count($parts) < 2) {
            return md5($publicKey);
        }

        $keyData = base64_decode($parts[1]);

        return implode(':', str_split(md5($keyData), 2));
    }
}
