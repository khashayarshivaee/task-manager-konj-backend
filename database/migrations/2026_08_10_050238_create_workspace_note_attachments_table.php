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
            'workspace_note_attachments',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('note_id')
                    ->constrained(
                        'workspace_notes'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId('uploaded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->string('disk', 32)
                    ->default('local');

                $table->string('path');

                $table->string(
                    'original_name',
                );

                $table->string(
                    'mime_type',
                    100,
                );

                $table
                    ->unsignedBigInteger(
                        'size'
                    );

                $table->timestamps();

                $table->index([
                    'note_id',
                    'created_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'workspace_note_attachments',
        );
    }
};
