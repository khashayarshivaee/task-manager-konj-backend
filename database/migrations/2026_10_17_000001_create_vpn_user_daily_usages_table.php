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
            'vpn_user_daily_usages',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('vpn_user_id')
                    ->constrained('vpn_users')
                    ->cascadeOnDelete();

                $table->date('usage_date');

                $table
                    ->unsignedBigInteger('downlink_bytes')
                    ->default(0);

                $table
                    ->unsignedBigInteger('uplink_bytes')
                    ->default(0);

                $table->timestamps();

                $table->unique([
                    'vpn_user_id',
                    'usage_date',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'vpn_user_daily_usages',
        );
    }
};
