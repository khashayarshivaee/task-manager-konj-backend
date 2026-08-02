<?php

declare(strict_types=1);

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title', 180);
            $table->text('description')->nullable();

            $table
                ->string('status', 30)
                ->default(TaskStatus::Backlog->value);

            $table
                ->string('priority', 20)
                ->default(TaskPriority::Medium->value);

            $table->date('starts_at')->nullable();
            $table->date('due_at')->nullable();

            $table
                ->timestamp('completed_at')
                ->nullable();

            $table
                ->unsignedInteger('position')
                ->default(0);

            $table->timestamps();

            $table->index([
                'project_id',
                'status',
                'position',
            ]);

            $table->index([
                'assigned_to',
                'status',
            ]);

            $table->index([
                'project_id',
                'due_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
