<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MonitoredServerSample extends Model
{
    protected $table = 'monitored_server_samples';

    protected $fillable = [
        'monitored_server_id',
        'cpu_percent',
        'memory_percent',
        'memory_used_bytes',
        'memory_total_bytes',
        'disk_percent',
        'disk_used_bytes',
        'disk_total_bytes',
        'uptime_seconds',
        'measured_at',
    ];

    protected function casts(): array
    {
        return [
            'monitored_server_id' => 'integer',

            'cpu_percent' => 'float',
            'memory_percent' => 'float',
            'disk_percent' => 'float',

            'memory_used_bytes' => 'integer',
            'memory_total_bytes' => 'integer',

            'disk_used_bytes' => 'integer',
            'disk_total_bytes' => 'integer',

            'uptime_seconds' => 'integer',

            'measured_at' => 'immutable_datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(
            MonitoredServer::class,
            'monitored_server_id',
        );
    }
}
