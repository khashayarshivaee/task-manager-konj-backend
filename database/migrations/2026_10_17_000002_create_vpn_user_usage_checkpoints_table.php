<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'vpn_user_usage_checkpoints',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('vpn_user_id')
                    ->unique()
                    ->constrained('vpn_users')
                    ->cascadeOnDelete();

                $table
                    ->unsignedBigInteger('last_downlink_bytes')
                    ->default(0);

                $table
                    ->unsignedBigInteger('last_uplink_bytes')
                    ->default(0);

                /*
                 * Detect Xray service restarts.
                 */
                $table
                    ->string('xray_invocation_id', 64)
                    ->nullable();

                /*
                 * Detect changes to the VPN user,
                 * including disable / enable.
                 */
                $table
                    ->timestamp('vpn_user_updated_at')
                    ->nullable();

                $table
                    ->timestamp('last_collected_at')
                    ->nullable();

                $table->timestamps();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'vpn_user_usage_checkpoints',
        );
    }
};
