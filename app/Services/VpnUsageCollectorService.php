<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class VpnUsageCollectorService
{
    public function __construct(
        private readonly XrayManagerService $xrayManagerService,
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

                $invocationBefore =
                    $this->getXrayInvocationId();

                $stats = $this->xrayManagerService
                    ->getUserStats($user);

                $invocationAfter =
                    $this->getXrayInvocationId();

                if (
                    $invocationBefore !==
                    $invocationAfter
                ) {
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
                    $invocationAfter,
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

    private function getXrayInvocationId(): string
    {
        $serviceName = trim(
            (string) config(
                'xray.service_name',
                'xray.service',
            ),
        );

        if ($serviceName === '') {
            throw new RuntimeException(
                'Xray service name is not configured.',
            );
        }

        $result = Process::timeout(5)->run([
            'systemctl',
            'show',
            $serviceName,
            '--property=InvocationID',
            '--value',
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to read Xray invocation ID.',
            );
        }

        $invocationId = strtolower(
            trim($result->output()),
        );

        if (
            preg_match(
                '/^[a-f0-9]{32}$/',
                $invocationId,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Invalid Xray invocation ID.',
            );
        }

        return $invocationId;
    }

    private function collectForUser(
        VpnUser $user,
        int $downlink,
        int $uplink,
        string $invocationId,
        string $userUpdatedAt,
    ): string {
        return DB::transaction(
            function () use (
                $user,
                $downlink,
                $uplink,
                $invocationId,
                $userUpdatedAt,
            ): string {
                $now = CarbonImmutable::now('UTC');

                $checkpoint = DB::table(
                    'vpn_user_usage_checkpoints',
                )
                    ->where('vpn_user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                /*
                 * First collection:
                 * establish the initial baseline.
                 */
                if ($checkpoint === null) {
                    DB::table(
                        'vpn_user_usage_checkpoints',
                    )->insert([
                        'vpn_user_id' => $user->id,

                        'last_downlink_bytes' =>
                            $downlink,

                        'last_uplink_bytes' =>
                            $uplink,

                        'xray_invocation_id' =>
                            $invocationId,

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

                $previousInvocation =
                    $checkpoint->xray_invocation_id;

                $previousUserUpdatedAt =
                    $checkpoint->vpn_user_updated_at;

                /*
                 * User state or user record changed.
                 *
                 * The counters may have restarted
                 * because the user was disabled and
                 * enabled again.
                 *
                 * Establish a fresh baseline rather
                 * than calculating an unsafe delta.
                 */
                if (
                    $previousUserUpdatedAt === null ||
                    $previousUserUpdatedAt !== $userUpdatedAt
                ) {
                    $this->updateCheckpoint(
                        (int) $checkpoint->id,
                        $downlink,
                        $uplink,
                        $invocationId,
                        $userUpdatedAt,
                        $now,
                    );

                    return 'baselined';
                }

                /*
                 * Existing checkpoint without an
                 * invocation ID.
                 */
                if (
                    $previousInvocation === null ||
                    $previousInvocation === ''
                ) {
                    $this->updateCheckpoint(
                        (int) $checkpoint->id,
                        $downlink,
                        $uplink,
                        $invocationId,
                        $userUpdatedAt,
                        $now,
                    );

                    return 'baselined';
                }

                /*
                 * A different invocation indicates
                 * that the Xray service restarted.
                 *
                 * Current runtime counters represent
                 * traffic from the new runtime.
                 */
                if (
                    $previousInvocation !==
                    $invocationId
                ) {
                    $downloadDelta = $downlink;
                    $uploadDelta = $uplink;
                } else {
                    /*
                     * Counter decreases within the
                     * same runtime are suspicious.
                     * Skip rather than double-count.
                     */
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
                    $invocationId,
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

                        'downlink_bytes' =>
                            $downloadDelta,

                        'uplink_bytes' =>
                            $uploadDelta,

                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table(
                        'vpn_user_daily_usages',
                    )
                        ->where(
                            'id',
                            $dailyRecord->id,
                        )
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
        string $invocationId,
        string $userUpdatedAt,
        CarbonImmutable $now,
    ): void {
        DB::table(
            'vpn_user_usage_checkpoints',
        )
            ->where('id', $checkpointId)
            ->update([
                'last_downlink_bytes' => $downlink,
                'last_uplink_bytes' => $uplink,

                'xray_invocation_id' =>
                    $invocationId,

                'vpn_user_updated_at' =>
                    $userUpdatedAt,

                'last_collected_at' => $now,
                'updated_at' => $now,
            ]);
    }
}
