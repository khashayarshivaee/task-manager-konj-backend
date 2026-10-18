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
        /*
         * Tracks Telegram notifications
         * independently from admin APNs alerts.
         */
        Schema::create(
            'vpn_telegram_usage_notifications',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('vpn_user_id')
                    ->constrained('vpn_users')
                    ->cascadeOnDelete();

                $table->bigInteger(
                    'telegram_chat_id',
                );

                $table->date('usage_date');

                $table->unsignedTinyInteger(
                    'threshold_gb',
                );

                $table->unsignedBigInteger(
                    'total_bytes',
                );

                $table->timestamp(
                    'sent_at',
                )->nullable();

                $table->timestamps();

                /*
                 * One notification per
                 * VPN user, day and threshold.
                 */
                $table->unique(
                    [
                        'vpn_user_id',
                        'usage_date',
                        'threshold_gb',
                    ],
                    'vpn_tg_usage_unique',
                );

                $table->index(
                    [
                        'usage_date',
                        'sent_at',
                    ],
                    'vpn_tg_usage_pending_idx',
                );
            },
        );

        /*
         * Capture usage at activation time.
         * Old consumption must not generate
         * unexpected Telegram messages.
         */
        Schema::create(
            'vpn_telegram_usage_baselines',
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
                    'vpn_tg_baseline_unique',
                );
            },
        );

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
                'vpn_telegram_usage_baselines',
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
            'vpn_telegram_usage_baselines',
        );

        Schema::dropIfExists(
            'vpn_telegram_usage_notifications',
        );
    }
};
