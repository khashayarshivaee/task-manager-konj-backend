<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProjectTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $search = trim(
            (string) $this->input(
                'search',
                ''
            )
        );

        $status = strtolower(
            trim(
                (string) $this->input(
                    'status',
                    ''
                )
            )
        );

        $priority = strtolower(
            trim(
                (string) $this->input(
                    'priority',
                    ''
                )
            )
        );

        $due = strtolower(
            trim(
                (string) $this->input(
                    'due',
                    ''
                )
            )
        );

        $assignee = strtolower(
            trim(
                (string) $this->input(
                    'assignee',
                    ''
                )
            )
        );

        if ($assignee === 'me') {
            $assignee =
                (string) $this->user()?->id;
        }

        $this->merge([
            'search' =>
                $search !== ''
                    ? $search
                    : null,

            'status' =>
                $status !== '' &&
                $status !== 'all'
                    ? $status
                    : null,

            'priority' =>
                $priority !== '' &&
                $priority !== 'all'
                    ? $priority
                    : null,

            'assignee' =>
                $assignee !== '' &&
                $assignee !== 'all'
                    ? $assignee
                    : null,

            'due' =>
                $due !== '' &&
                $due !== 'all'
                    ? $due
                    : null,

            'sort' => strtolower(
                (string) $this->input(
                    'sort',
                    'workflow'
                )
            ),

            'direction' => strtolower(
                (string) $this->input(
                    'direction',
                    'asc'
                )
            ),

            'page' => $this->input(
                'page',
                1
            ),

            'per_page' => $this->input(
                'per_page',
                20
            ),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Workspace|null $workspace */
        $workspace =
            $this->route('workspace');

        return [
            'search' => [
                'nullable',
                'string',
                'max:180',
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

            'assignee' => [
                'nullable',
                'integer',

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
            ],

            'due' => [
                'nullable',

                Rule::in([
                    'this_week',
                    'this_month',
                    'this_year',
                    'overdue',
                    'no_due_date',
                ]),
            ],

            'sort' => [
                'required',

                Rule::in([
                    'workflow',
                    'status',
                    'priority',
                    'due_at',
                    'title',
                    'created_at',
                    'updated_at',
                ]),
            ],

            'direction' => [
                'required',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'page' => [
                'required',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
