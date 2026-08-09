<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table
                ->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type', 80);

            $table
                ->string('subject_type', 80)
                ->nullable();

            $table
                ->unsignedBigInteger('subject_id')
                ->nullable();

            $table
                ->string('subject_label', 255)
                ->nullable();

            $table
                ->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'project_id',
                'created_at',
            ]);

            $table->index([
                'project_id',
                'type',
            ]);

            $table->index([
                'subject_type',
                'subject_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'project_activities'
        );
    }
};
