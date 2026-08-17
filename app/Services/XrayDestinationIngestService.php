<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VpnUser;
use App\Models\VpnUserDestination;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class XrayDestinationIngestService
{
    public function __construct(
        private readonly XrayDestinationLogReader $logReader,
    ) {
    }

    public function ingestLine(
        string $line,
    ): bool {
        $parsed = $this->logReader->parseLine(
            $line,
        );

        if ($parsed === null) {
            return false;
        }

        $vpnUser = VpnUser::query()
            ->where(
                'xray_email',
                $parsed['xray_email'],
            )
            ->first();

        if ($vpnUser === null) {
            return false;
        }

        $destination = $this->normalizeDestination(
            $parsed['destination'],
        );

        if ($destination === '') {
            return false;
        }

        DB::transaction(
            function () use (
                $vpnUser,
                $destination,
                $parsed,
            ): void {
                $record = VpnUserDestination::query()
                    ->lockForUpdate()
                    ->firstOrNew([
                        'vpn_user_id' =>
                            $vpnUser->id,

                        'destination' =>
                            $destination,
                    ]);

                if (! $record->exists) {
                    $record->connection_count = 0;

                    $record->first_seen_at =
                        $parsed['occurred_at'];
                }

                $record->connection_count =
                    (int) $record->connection_count + 1;

                if (
                    $record->first_seen_at === null ||
                    $parsed['occurred_at']->lt(
                        $record->first_seen_at,
                    )
                ) {
                    $record->first_seen_at =
                        $parsed['occurred_at'];
                }

                if (
                    $record->last_seen_at === null ||
                    $parsed['occurred_at']->gt(
                        $record->last_seen_at,
                    )
                ) {
                    $record->last_seen_at =
                        $parsed['occurred_at'];
                }

                $record->save();
            },
        );

        return true;
    }

    private function normalizeDestination(
        string $destination,
    ): string {
        $destination = strtolower(
            trim($destination),
        );

        $destination = rtrim(
            $destination,
            '.',
        );

        return $destination;
    }
}
