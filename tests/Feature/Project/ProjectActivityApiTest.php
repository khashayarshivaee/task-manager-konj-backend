<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\ProjectActivitySubjectType;
use App\Enums\ProjectActivityType;
use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectActivityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_list_project_activities(): void
    {
        $owner =
            User::factory()->create();

        $member =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $member->id,

                'role' =>
                    WorkspaceRole::Member,

                'joined_at' =>
                    now(),
            ]);

        $project =
            $this->createProject(
                $workspace,
                $owner
            );

        $firstActivity =
            ProjectActivity::query()
                ->create([
                    'project_id' =>
                        $project->id,

                    'actor_id' =>
                        $owner->id,

                    'type' =>
                        ProjectActivityType::ProjectCreated,

                    'subject_type' =>
                        ProjectActivitySubjectType::Project,

                    'subject_id' =>
                        $project->id,

                    'subject_label' =>
                        $project->name,
                ]);

        $secondActivity =
            ProjectActivity::query()
                ->create([
                    'project_id' =>
                        $project->id,

                    'actor_id' =>
                        $member->id,

                    'type' =>
                        ProjectActivityType::TaskCreated,

                    'subject_type' =>
                        ProjectActivitySubjectType::Task,

                    'subject_id' =>
                        100,

                    'subject_label' =>
                        'Newest task',
                ]);

        Sanctum::actingAs(
            $member
        );

        $this->getJson(
            $this->activitiesUrl(
                $workspace,
                $project
            )
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.activities'
            )
            ->assertJsonPath(
                'data.activities.0.id',
                $secondActivity->id
            )
            ->assertJsonPath(
                'data.activities.0.type',
                ProjectActivityType::TaskCreated->value
            )
            ->assertJsonPath(
                'data.activities.0.subject_label',
                'Newest task'
            )
            ->assertJsonPath(
                'data.activities.0.actor.id',
                $member->id
            )
            ->assertJsonPath(
                'data.activities.0.actor.name',
                $member->name
            )
            ->assertJsonPath(
                'data.activities.1.id',
                $firstActivity->id
            )
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            );
    }

    public function test_project_activities_are_paginated(): void
    {
        $owner =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $project =
            $this->createProject(
                $workspace,
                $owner
            );

        for ($index = 1; $index <= 5; $index++) {
            ProjectActivity::query()
                ->create([
                    'project_id' =>
                        $project->id,

                    'actor_id' =>
                        $owner->id,

                    'type' =>
                        ProjectActivityType::TaskCreated,

                    'subject_type' =>
                        ProjectActivitySubjectType::Task,

                    'subject_id' =>
                        $index,

                    'subject_label' =>
                        "Task {$index}",
                ]);
        }

        Sanctum::actingAs(
            $owner
        );

        $this->getJson(
            $this->activitiesUrl(
                $workspace,
                $project
            )
            .'?page=2&per_page=2'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.activities'
            )
            ->assertJsonPath(
                'data.activities.0.subject_label',
                'Task 3'
            )
            ->assertJsonPath(
                'data.activities.1.subject_label',
                'Task 2'
            )
            ->assertJsonPath(
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                5
            )
            ->assertJsonPath(
                'meta.last_page',
                3
            )
            ->assertJsonPath(
                'meta.has_more_pages',
                true
            );
    }

    public function test_outsider_cannot_view_project_activities(): void
    {
        $owner =
            User::factory()->create();

        $outsider =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $project =
            $this->createProject(
                $workspace,
                $owner
            );

        Sanctum::actingAs(
            $outsider
        );

        $this->getJson(
            $this->activitiesUrl(
                $workspace,
                $project
            )
        )->assertForbidden();
    }

    public function test_project_activities_cannot_be_accessed_through_another_workspace(): void
    {
        $firstOwner =
            User::factory()->create();

        $secondOwner =
            User::factory()->create();

        $firstWorkspace =
            $this->createWorkspace(
                $firstOwner,
                'First Workspace',
                'first-workspace'
            );

        $secondWorkspace =
            $this->createWorkspace(
                $secondOwner,
                'Second Workspace',
                'second-workspace'
            );

        $project =
            $this->createProject(
                $secondWorkspace,
                $secondOwner
            );

        Sanctum::actingAs(
            $firstOwner
        );

        $this->getJson(
            $this->activitiesUrl(
                $firstWorkspace,
                $project
            )
        )->assertNotFound();
    }

    private function createWorkspace(
        User $owner,
        string $name = 'Activity Workspace',
        string $slug = 'activity-workspace'
    ): Workspace {
        $workspace =
            Workspace::query()->create([
                'owner_id' =>
                    $owner->id,

                'name' =>
                    $name,

                'slug' =>
                    $slug,
            ]);

        $workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $owner->id,

                'role' =>
                    WorkspaceRole::Owner,

                'joined_at' =>
                    now(),
            ]);

        return $workspace;
    }

    private function createProject(
        Workspace $workspace,
        User $creator
    ): Project {
        return Project::query()->create([
            'workspace_id' =>
                $workspace->id,

            'created_by' =>
                $creator->id,

            'name' =>
                'Activity Project',

            'slug' =>
                'activity-project',

            'status' =>
                ProjectStatus::Active,
        ]);
    }

    private function activitiesUrl(
        Workspace $workspace,
        Project $project
    ): string {
        return "/api/workspaces/"
            ."{$workspace->id}"
            ."/projects/"
            ."{$project->id}"
            ."/activities";
    }
}
