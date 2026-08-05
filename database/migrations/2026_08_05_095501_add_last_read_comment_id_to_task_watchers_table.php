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
        Schema::table(
            'task_watchers',
            function (Blueprint $table): void {
                $table
                    ->foreignId(
                        'last_read_comment_id',
                    )
                    ->nullable()
                    ->after('user_id')
                    ->constrained(
                        'task_comments',
                    )
                    ->nullOnDelete();
            },
        );

        $latestComments = DB::table(
            'task_comments',
        )
            ->select([
                'task_id',

                DB::raw(
                    'MAX(id) AS last_comment_id',
                ),
            ])
            ->groupBy('task_id')
            ->get();

        foreach (
            $latestComments as $latestComment
        ) {
            DB::table('task_watchers')
                ->where(
                    'task_id',
                    $latestComment->task_id,
                )
                ->update([
                    'last_read_comment_id' =>
                        $latestComment
                            ->last_comment_id,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table(
            'task_watchers',
            function (Blueprint $table): void {
                $table->dropConstrainedForeignId(
                    'last_read_comment_id',
                );
            },
        );
    }
};
