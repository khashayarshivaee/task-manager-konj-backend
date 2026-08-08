<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $description = trim(
            (string) $this->input(
                'description',
                ''
            )
        );

        $startsAt = trim(
            (string) $this->input(
                'starts_at',
                ''
            )
        );

        $dueAt = trim(
            (string) $this->input(
                'due_at',
                ''
            )
        );

        $input = $this->all();

        if (
            array_key_exists(
                'assignee_ids',
                $input
            )
        ) {
            $assigneeIds =
                $input['assignee_ids'];
        } else {
            $legacyAssigneeId =
                $this->input('assigned_to');

            $assigneeIds = (
                $legacyAssigneeId !== null &&
                $legacyAssigneeId !== ''
            )
                ? [$legacyAssigneeId]
                : [];
        }

        $this->merge([
            'title' => trim(
                (string) $this->input('title')
            ),

            'description' =>
                $description !== ''
                    ? $description
                    : null,

            'assignee_ids' => $assigneeIds,

            'starts_at' =>
                $startsAt !== ''
                    ? $startsAt
                    : null,

            'due_at' =>
                $dueAt !== ''
                    ? $dueAt
                    : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
       /** @var Workspace|null $workspace */
       $workspace = $this->route(
           'workspace'
       );

       /** @var Project|null $project */
       $project = $this->route(
           'project'
       );

        return [
            'title' => [
                'required',
                'string',
                'min:2',
                'max:180',
            ],

            'description' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'status' => [
                'nullable',
                Rule::enum(
                    TaskStatus::class
                ),
            ],

            'priority' => [
                'nullable',
                Rule::enum(
                    TaskPriority::class
                ),
            ],

            'assignee_ids' => [
                'array',
                'max:50',
            ],

          'assignee_ids.*' => [
              'integer',
              'distinct',

              Rule::exists(
                  'workspace_memberships',
                  'user_id'
              )->where(
                  fn ($query) =>
                      $query->where(
                          'workspace_id',
                          $workspace?->id
                      )
              ),

              Rule::exists(
                  'project_memberships',
                  'user_id'
              )->where(
                  fn ($query) =>
                      $query->where(
                          'project_id',
                          $project?->id
                      )
              ),

              Rule::exists(
                  'users',
                  'id'
              )->where(
                  fn ($query) =>
                      $query->where(
                          'is_active',
                          true
                      )
              ),
          ],

            'starts_at' => [
                'nullable',
                'date',
            ],

            'due_at' => [
                'nullable',
                'date',

                Rule::when(
                    $this->filled(
                        'starts_at'
                    ),
                    [
                        'after_or_equal:starts_at',
                    ]
                ),
            ],
        ];
    }
}
