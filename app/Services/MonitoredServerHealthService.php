<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoredServer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class MonitoredServerHealthService
{
    private const MAX_AGE_MINUTES = 3;

    private const MAX_FUTURE_SECONDS = 60;

    public function getLatest(
        MonitoredServer $server,
    ): ?array {
        $health = Cache::get(
            $this->cacheKey($server),
        );

        if (!is_array($health)) {
            return null;
        }

        if (!$this->isFresh($health)) {
            return null;
        }

        return $health;
    }

    public function cacheKey(
        MonitoredServer $server,
    ): string {
        // Preserve the existing Iran cache.
        if (
            $server->slug === 'iran' &&
            $server->driver === 'local'
        ) {
            return 'server-status:health:latest';
        }

        // Isolated cache for every other server.
        return sprintf(
            'server-status:servers:%s:health:latest',
            $server->slug,
        );
    }

    private function isFresh(array $health): bool
    {
        $measuredAt = $health['measured_at'] ?? null;

        if (
            !is_string($measuredAt) ||
            trim($measuredAt) === ''
        ) {
            return false;
        }

        try {
            $measured = CarbonImmutable::parse(
                $measuredAt,
            )->utc();
        } catch (Throwable) {
            return false;
        }

        $now = CarbonImmutable::now('UTC');

        if (
            $measured->lessThan(
                $now->subMinutes(self::MAX_AGE_MINUTES),
            )
        ) {
            return false;
        }

        if (
            $measured->greaterThan(
                $now->addSeconds(self::MAX_FUTURE_SECONDS),
            )
        ) {
            return false;
        }

        return true;
    }
}
