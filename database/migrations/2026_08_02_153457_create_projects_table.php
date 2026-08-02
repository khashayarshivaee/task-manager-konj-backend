<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();

            $table
                ->string('status', 30)
                ->default(ProjectStatus::Planning->value);

            $table->string('color', 20)->nullable();

            $table->date('starts_at')->nullable();
            $table->date('due_at')->nullable();

            $table->timestamps();

            $table->unique([
                'workspace_id',
                'slug',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
