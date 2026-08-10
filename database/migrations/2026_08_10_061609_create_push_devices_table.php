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
            'push_devices',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string(
                    'token',
                    512,
                );

                $table
                    ->string(
                        'platform',
                        20,
                    )
                    ->default('ios');

                $table->string(
                    'environment',
                    20,
                );

                $table
                    ->string(
                        'device_name',
                        120,
                    )
                    ->nullable();

                $table
                    ->boolean('is_active')
                    ->default(true);

                $table
                    ->timestamp(
                        'last_seen_at',
                    )
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'token',
                    'environment',
                ]);

                $table->index([
                    'user_id',
                    'is_active',
                    'environment',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'push_devices',
        );
    }
};
