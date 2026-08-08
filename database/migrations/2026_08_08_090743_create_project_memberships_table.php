<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'project_memberships',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('project_id')
                    ->constrained('projects')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('added_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->timestamp('joined_at')
                    ->useCurrent();

                $table->timestamps();

                $table->unique([
                    'project_id',
                    'user_id',
                ]);

                $table->index([
                    'user_id',
                    'project_id',
                ]);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'project_memberships'
        );
    }
};
