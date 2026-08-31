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
            'instagram_access_grants',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('workspace_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('granted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'workspace_id',
                        'user_id',
                    ],
                    'instagram_access_workspace_user_unique',
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'instagram_access_grants'
        );
    }
};
