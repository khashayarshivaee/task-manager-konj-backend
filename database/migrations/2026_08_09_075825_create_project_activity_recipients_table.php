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
            'project_activity_recipients',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'project_activity_id',
                    )
                    ->constrained(
                        'project_activities',
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table
                    ->timestamp('read_at')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'project_activity_id',
                    'user_id',
                ]);

                $table->index([
                    'user_id',
                    'read_at',
                ]);

                $table->index([
                    'user_id',
                    'created_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'project_activity_recipients',
        );
    }
};
