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
            'monitored_server_samples',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('monitored_server_id')
                    ->constrained('monitored_servers')
                    ->cascadeOnDelete();

                $table->decimal('cpu_percent', 5, 2);

                $table->decimal('memory_percent', 5, 2);
                $table->unsignedBigInteger('memory_used_bytes');
                $table->unsignedBigInteger('memory_total_bytes');

                $table->decimal('disk_percent', 5, 2);
                $table->unsignedBigInteger('disk_used_bytes');
                $table->unsignedBigInteger('disk_total_bytes');

                $table->unsignedBigInteger('uptime_seconds');

                // Store measurement time in UTC.
                $table->dateTime('measured_at');

                $table->timestamps();

                $table->unique(
                    ['monitored_server_id', 'measured_at'],
                    'monitored_server_sample_unique',
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'monitored_server_samples',
        );
    }
};
