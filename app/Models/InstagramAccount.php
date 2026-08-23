<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class InstagramAccount extends Model
{
    protected $fillable = [
        'workspace_id',
        'instagram_id',
        'username',
        'access_token_encrypted',
        'token_expires_at',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token_encrypted',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function setAccessToken(string $token): void
    {
        $this->access_token_encrypted = Crypt::encryptString($token);
    }

    public function getAccessToken(): ?string
    {
        if (!$this->access_token_encrypted) {
            return null;
        }

        return Crypt::decryptString(
            $this->access_token_encrypted
        );
    }
}
