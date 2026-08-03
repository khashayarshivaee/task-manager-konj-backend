<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'task_assignees',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('task_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('assigned_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique([
                    'task_id',
                    'user_id',
                ]);
            }
        );

        Schema::create(
            'task_watchers',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('task_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->timestamps();

                $table->unique([
                    'task_id',
                    'user_id',
                ]);
            }
        );

        $now = now();

        DB::table('tasks')
            ->select([
                'id',
                'created_by',
                'assigned_to',
            ])
            ->orderBy('id')
            ->chunkById(
                200,
                function ($tasks) use ($now): void {
                    $assignees = [];
                    $watchers = [];

                    foreach ($tasks as $task) {
                        if ($task->assigned_to !== null) {
                            $assignees[] = [
                                'task_id' =>
                                    $task->id,

                                'user_id' =>
                                    $task->assigned_to,

                                'assigned_by' =>
                                    $task->created_by,

                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            $watchers[] = [
                                'task_id' =>
                                    $task->id,

                                'user_id' =>
                                    $task->assigned_to,

                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        if ($task->created_by !== null) {
                            $watchers[] = [
                                'task_id' =>
                                    $task->id,

                                'user_id' =>
                                    $task->created_by,

                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    if ($assignees !== []) {
                        DB::table('task_assignees')
                            ->insertOrIgnore(
                                $assignees
                            );
                    }

                    if ($watchers !== []) {
                        DB::table('task_watchers')
                            ->insertOrIgnore(
                                $watchers
                            );
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'task_watchers'
        );

        Schema::dropIfExists(
            'task_assignees'
        );
    }
};
