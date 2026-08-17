<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnUserDestination extends Model
{
    protected $fillable = [
        'vpn_user_id',
        'destination',
        'connection_count',
        'total_duration_seconds',
        'first_seen_at',
        'last_seen_at',
        'uplink_bytes',
        'downlink_bytes',
        'total_bytes',
    ];

    protected function casts(): array
    {
        return [
            'connection_count' => 'integer',

            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'total_duration_seconds' => 'integer',
            'uplink_bytes' => 'integer',
            'downlink_bytes' => 'integer',
            'total_bytes' => 'integer',
        ];
    }

    public function vpnUser(): BelongsTo
    {
        return $this->belongsTo(
            VpnUser::class,
        );
    }

    public function scopeRecentlySeen(
        Builder $query,
    ): Builder {
        return $query
            ->orderByDesc('last_seen_at');
    }

    public function scopeMostUsed(
        Builder $query,
    ): Builder {
        return $query
            ->orderByDesc('total_bytes')
            ->orderByDesc('connection_count');
    }
}
