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
            'workspace_notes',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('workspace_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('author_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string(
                    'title',
                    180,
                );

                $table
                    ->longText('content')
                    ->nullable();

                $table
                    ->boolean('is_pinned')
                    ->default(false);

                $table->timestamps();

                $table->index([
                    'workspace_id',
                    'is_pinned',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'workspace_notes',
        );
    }
};
