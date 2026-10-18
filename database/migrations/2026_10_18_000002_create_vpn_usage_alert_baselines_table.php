<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'vpn_usage_alert_baselines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('vpn_user_id')
                    ->constrained('vpn_users')
                    ->cascadeOnDelete();

                $table->date('usage_date');

                $table->unsignedBigInteger(
                    'initial_total_bytes',
                );

                $table->timestamps();

                $table->unique(
                    [
                        'vpn_user_id',
                        'usage_date',
                    ],
                    'vpn_usage_alert_baseline_unique',
                );
            },
        );

        /*
         * Snapshot existing usage at the
         * moment this migration is executed.
         *
         * Historical usage must not
         * trigger new push notifications.
         */
        $today = CarbonImmutable::today('UTC')
            ->toDateString();

        $now = CarbonImmutable::now('UTC');

        $usages = DB::table(
            'vpn_user_daily_usages',
        )
            ->where('usage_date', $today)
            ->get([
                'vpn_user_id',
                'downlink_bytes',
                'uplink_bytes',
            ]);

        foreach ($usages as $usage) {
            DB::table(
                'vpn_usage_alert_baselines',
            )->insertOrIgnore([
                'vpn_user_id' =>
                    (int) $usage->vpn_user_id,

                'usage_date' => $today,

                'initial_total_bytes' =>
                    (int) $usage->downlink_bytes
                    + (int) $usage->uplink_bytes,

                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'vpn_usage_alert_baselines',
        );
    }
};
