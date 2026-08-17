<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_user_destinations', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('vpn_user_id')
                ->constrained('vpn_users')
                ->cascadeOnDelete();

            $table->string('destination', 255);

            $table
                ->unsignedBigInteger('connection_count')
                ->default(0);

            $table
                ->unsignedBigInteger('total_duration_seconds')
                ->default(0);

            $table
                ->timestamp('first_seen_at')
                ->nullable();

            $table
                ->timestamp('last_seen_at')
                ->nullable();

            /*
             * Traffic accounting
             * will be populated in the next phase.
             */
            $table
                ->unsignedBigInteger('uplink_bytes')
                ->default(0);

            $table
                ->unsignedBigInteger('downlink_bytes')
                ->default(0);

            $table
                ->unsignedBigInteger('total_bytes')
                ->default(0);

            $table->timestamps();

            $table->unique([
                'vpn_user_id',
                'destination',
            ]);

            $table->index([
                'vpn_user_id',
                'last_seen_at',
            ]);

            $table->index([
                'vpn_user_id',
                'total_bytes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'vpn_user_destinations',
        );
    }
};
