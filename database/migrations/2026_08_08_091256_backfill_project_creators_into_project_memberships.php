<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add existing project creators
     * as the first project members.
     */
    public function up(): void
    {
        $now = now();

        DB::table('projects')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($projects) use ($now): void {
                    $rows = [];

                    foreach ($projects as $project) {
                        $isWorkspaceMember =
                            DB::table(
                                'workspace_memberships'
                            )
                                ->where(
                                    'workspace_id',
                                    $project->workspace_id
                                )
                                ->where(
                                    'user_id',
                                    $project->created_by
                                )
                                ->exists();

                        if (!$isWorkspaceMember) {
                            continue;
                        }

                        $rows[] = [
                            'project_id' =>
                                $project->id,

                            'user_id' =>
                                $project->created_by,

                            'added_by' =>
                                $project->created_by,

                            'joined_at' => $now,

                            'created_at' => $now,

                            'updated_at' => $now,
                        ];
                    }

                    if ($rows !== []) {
                        DB::table(
                            'project_memberships'
                        )->insertOrIgnore($rows);
                    }
                }
            );
    }

    /**
     * Do not remove project memberships
     * during rollback because memberships
     * may have changed after migration.
     */
    public function down(): void
    {
        //
    }
};
