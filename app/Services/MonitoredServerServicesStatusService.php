<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoredServer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class MonitoredServerServicesStatusService
{
    private const MAX_AGE_MINUTES = 3;

    private const MAX_FUTURE_SECONDS = 60;

    public function getLatest(
        MonitoredServer $server,
    ): ?array {
        $snapshot = Cache::get(
            $this->cacheKey($server),
        );

        if (
            !is_array($snapshot) ||
            !isset($snapshot['services']) ||
            !is_array($snapshot['services'])
        ) {
            return null;
        }

        if (!$this->isFresh($snapshot)) {
            return null;
        }

        return $snapshot;
    }

    public function cacheKey(
        MonitoredServer $server,
    ): string {
        // Preserve existing Iran services cache.
        if (
            $server->slug === 'iran' &&
            $server->driver === 'local'
        ) {
            return 'server-status:services:latest';
        }

        // Independent cache for remote servers.
        return sprintf(
            'server-status:servers:%s:services:latest',
            $server->slug,
        );
    }

    private function isFresh(
        array $snapshot,
    ): bool {
        $checkedAt = $snapshot['checked_at'] ?? null;

        if (
            !is_string($checkedAt) ||
            trim($checkedAt) === ''
        ) {
            return false;
        }

        try {
            $checked = CarbonImmutable::parse(
                $checkedAt,
            )->utc();
        } catch (Throwable) {
            return false;
        }

        $now = CarbonImmutable::now('UTC');

        if (
            $checked->lessThan(
                $now->subMinutes(self::MAX_AGE_MINUTES),
            )
        ) {
            return false;
        }

        if (
            $checked->greaterThan(
                $now->addSeconds(self::MAX_FUTURE_SECONDS),
            )
        ) {
            return false;
        }

        return true;
    }
}
