<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_belongs_to_workspace_and_creator(): void
    {
        $user = User::factory()->create();

        $workspace = Workspace::query()->create([
            'owner_id' => $user->id,
            'name' => 'Konj Workspace',
            'slug' => 'konj-workspace',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Task Manager',
            'slug' => 'task-manager',
            'description' => 'Konj task management project.',
            'status' => ProjectStatus::Active,
            'color' => '#ff6b00',
            'starts_at' => now()->toDateString(),
            'due_at' => now()
                ->addMonth()
                ->toDateString(),
        ]);

        $project->load([
            'workspace',
            'creator',
        ]);

        $this->assertTrue(
            $project->workspace->is($workspace)
        );

        $this->assertTrue(
            $project->creator->is($user)
        );

        $this->assertSame(
            ProjectStatus::Active,
            $project->status
        );

        $this->assertTrue(
            $workspace
                ->projects()
                ->whereKey($project->id)
                ->exists()
        );

        $this->assertTrue(
            $user
                ->createdProjects()
                ->whereKey($project->id)
                ->exists()
        );
    }
}
