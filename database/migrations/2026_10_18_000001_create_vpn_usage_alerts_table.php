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
            'vpn_usage_alerts',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('vpn_user_id')
                    ->nullable()
                    ->constrained('vpn_users')
                    ->nullOnDelete();

                $table->foreignId('recipient_user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('vpn_user_name', 100);

                $table->date('usage_date');

                $table->unsignedTinyInteger(
                    'threshold_gb',
                );

                $table->unsignedBigInteger(
                    'total_bytes',
                );

                $table->timestamp('read_at')
                    ->nullable();

                $table->timestamp('push_sent_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'vpn_user_id',
                        'usage_date',
                        'threshold_gb',
                    ],
                    'vpn_usage_alert_unique',
                );

                $table->index([
                    'recipient_user_id',
                    'read_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'vpn_usage_alerts',
        );
    }
};
