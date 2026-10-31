<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class VpnUsageCollectorService
{
    public function __construct(
        private readonly XrayManagerService $xrayManagerService,
        private readonly XrayRuntimeService $xrayRuntimeService,
    ) {
    }

    public function collect(): array
    {
        $result = [
            'baselined' => 0,
            'collected' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $users = VpnUser::query()
            ->active()
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            try {
                $userUpdatedAt = $user->getRawOriginal(
                    'updated_at',
                );

                if (
                    ! is_string($userUpdatedAt) ||
                    trim($userUpdatedAt) === ''
                ) {
                    throw new RuntimeException(
                        'VPN user update timestamp is unavailable.',
                    );
                }

                // Read uptime before and after the user's counters.
                // A restart during the read makes that sample unsafe.
                $uptimeBefore = $this->xrayRuntimeService
                    ->getUptimeSeconds();

                $stats = $this->xrayManagerService
                    ->getUserStats($user);

                $uptimeAfter = $this->xrayRuntimeService
                    ->getUptimeSeconds();

                if ($uptimeAfter < $uptimeBefore) {
                    $result['skipped']++;

                    continue;
                }

                $downlink = max(
                    0,
                    (int) $stats['downlink_bytes'],
                );

                $uplink = max(
                    0,
                    (int) $stats['uplink_bytes'],
                );

                $status = $this->collectForUser(
                    $user,
                    $downlink,
                    $uplink,
                    $uptimeAfter,
                    $userUpdatedAt,
                );

                $result[$status]++;
            } catch (Throwable $exception) {
                report($exception);

                $result['failed']++;
            }
        }

        return $result;
    }

    private function collectForUser(
        VpnUser $user,
        int $downlink,
        int $uplink,
        int $uptimeSeconds,
        string $userUpdatedAt,
    ): string {
        return DB::transaction(
            function () use (
                $user,
                $downlink,
                $uplink,
                $uptimeSeconds,
                $userUpdatedAt,
            ): string {
                $now = CarbonImmutable::now('UTC');

                $checkpoint = DB::table(
                    'vpn_user_usage_checkpoints',
                )
                    ->where('vpn_user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                // First collection: establish a baseline only.
                if ($checkpoint === null) {
                    DB::table(
                        'vpn_user_usage_checkpoints',
                    )->insert([
                        'vpn_user_id' => $user->id,
                        'last_downlink_bytes' => $downlink,
                        'last_uplink_bytes' => $uplink,
                        'xray_last_uptime_seconds' =>
                            $uptimeSeconds,
                        'vpn_user_updated_at' =>
                            $userUpdatedAt,
                        'last_collected_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return 'baselined';
                }

                $previousDownlink =
                    (int) $checkpoint->last_downlink_bytes;

                $previousUplink =
                    (int) $checkpoint->last_uplink_bytes;

                $previousUptime =
                    $checkpoint->xray_last_uptime_seconds;

                $previousCollectedAt =
                    $checkpoint->last_collected_at;

                $previousUserUpdatedAt =
                    $checkpoint->vpn_user_updated_at;

                // A disabled/enabled or edited user
                // needs a fresh baseline.
                if (
                    $previousUserUpdatedAt === null ||
                    $previousUserUpdatedAt !== $userUpdatedAt
                ) {
                    $this->updateCheckpoint(
                        (int) $checkpoint->id,
                        $downlink,
                        $uplink,
                        $uptimeSeconds,
                        $userUpdatedAt,
                        $now,
                    );

                    return 'baselined';
                }

                // Migration from the old local systemctl
                // invocation ID. Do not count old counters again.
                if (
                    $previousUptime === null ||
                    $previousCollectedAt === null
                ) {
                    $this->updateCheckpoint(
                        (int) $checkpoint->id,
                        $downlink,
                        $uplink,
                        $uptimeSeconds,
                        $userUpdatedAt,
                        $now,
                    );

                    return 'baselined';
                }

                $lastCollectedTimestamp = CarbonImmutable::parse(
                    (string) $previousCollectedAt,
                    'UTC',
                )->getTimestamp();

                $elapsedSeconds = max(
                    0,
                    $now->getTimestamp() - $lastCollectedTimestamp,
                );

                $hasRestarted = $this->xrayRuntimeService
                    ->hasRestarted(
                        (int) $previousUptime,
                        $uptimeSeconds,
                        $elapsedSeconds,
                    );

                if ($hasRestarted) {
                    // A new runtime starts with new counters.
                    $downloadDelta = $downlink;
                    $uploadDelta = $uplink;
                } else {
                    // Skip unexpected counter decreases
                    // to prevent double-counting.
                    if (
                        $downlink < $previousDownlink ||
                        $uplink < $previousUplink
                    ) {
                        return 'skipped';
                    }

                    $downloadDelta =
                        $downlink - $previousDownlink;

                    $uploadDelta =
                        $uplink - $previousUplink;
                }

                $this->updateCheckpoint(
                    (int) $checkpoint->id,
                    $downlink,
                    $uplink,
                    $uptimeSeconds,
                    $userUpdatedAt,
                    $now,
                );

                if (
                    $downloadDelta === 0 &&
                    $uploadDelta === 0
                ) {
                    return 'collected';
                }

                $usageDate = $now->toDateString();

                $dailyQuery = DB::table(
                    'vpn_user_daily_usages',
                )
                    ->where('vpn_user_id', $user->id)
                    ->where('usage_date', $usageDate);

                $dailyRecord = (clone $dailyQuery)
                    ->lockForUpdate()
                    ->first(['id']);

                if ($dailyRecord === null) {
                    DB::table(
                        'vpn_user_daily_usages',
                    )->insert([
                        'vpn_user_id' => $user->id,
                        'usage_date' => $usageDate,
                        'downlink_bytes' => $downloadDelta,
                        'uplink_bytes' => $uploadDelta,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table(
                        'vpn_user_daily_usages',
                    )
                        ->where('id', $dailyRecord->id)
                        ->incrementEach(
                            [
                                'downlink_bytes' =>
                                    $downloadDelta,
                                'uplink_bytes' =>
                                    $uploadDelta,
                            ],
                            [
                                'updated_at' => $now,
                            ],
                        );
                }

                return 'collected';
            },
        );
    }

    private function updateCheckpoint(
        int $checkpointId,
        int $downlink,
        int $uplink,
        int $uptimeSeconds,
        string $userUpdatedAt,
        CarbonImmutable $now,
    ): void {
        DB::table('vpn_user_usage_checkpoints')
            ->where('id', $checkpointId)
            ->update([
                'last_downlink_bytes' => $downlink,
                'last_uplink_bytes' => $uplink,
                'xray_last_uptime_seconds' => $uptimeSeconds,
                'vpn_user_updated_at' => $userUpdatedAt,
                'last_collected_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
