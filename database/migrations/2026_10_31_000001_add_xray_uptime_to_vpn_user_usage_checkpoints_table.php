<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'vpn_user_usage_checkpoints',
            function (Blueprint $table): void {
                $table
                    ->unsignedBigInteger('xray_last_uptime_seconds')
                    ->nullable();
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'vpn_user_usage_checkpoints',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'xray_last_uptime_seconds',
                );
            },
        );
    }
};
