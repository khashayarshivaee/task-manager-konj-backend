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
            'workspace_memberships',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('workspace_id')
                    ->constrained('workspaces')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('role', 20)
                    ->default('member');

                $table->timestamp('joined_at')
                    ->useCurrent();

                $table->timestamps();

                $table->unique([
                    'workspace_id',
                    'user_id',
                ]);

                $table->index([
                    'user_id',
                    'role',
                ]);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_memberships');
    }
};
