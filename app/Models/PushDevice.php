<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDevice extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'environment',
        'device_name',
        'is_active',
        'last_seen_at',
    ];

    protected $hidden = [
        'token',
    ];

    /**
     * User that currently owns
     * this installation/token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
