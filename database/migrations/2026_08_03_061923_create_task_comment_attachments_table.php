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
            'task_comment_attachments',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('comment_id')
                    ->constrained('task_comments')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('uploaded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->string('disk', 50);

                $table
                    ->string('path', 2048);

                $table
                    ->string('original_name');

                $table
                    ->string('mime_type', 100);

                $table
                    ->unsignedBigInteger('size');

                $table->timestamps();

                $table->index(
                    [
                        'comment_id',
                        'created_at',
                    ],
                    'comment_attachments_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'task_comment_attachments',
        );
    }
};
