<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $description = trim(
            (string) $this->input('description', '')
        );

        $startsAt = trim(
            (string) $this->input('starts_at', '')
        );

        $dueAt = trim(
            (string) $this->input('due_at', '')
        );

        $this->merge([
            'title' => trim(
                (string) $this->input('title')
            ),

            'description' => $description !== ''
                ? $description
                : null,

            'starts_at' => $startsAt !== ''
                ? $startsAt
                : null,

            'due_at' => $dueAt !== ''
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
        $workspace = $this->route('workspace');

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
                'required',
                Rule::enum(TaskStatus::class),
            ],

            'priority' => [
                'required',
                Rule::enum(TaskPriority::class),
            ],

            'assigned_to' => [
                'nullable',
                'integer',

                Rule::exists(
                    'workspace_memberships',
                    'user_id'
                )->where(
                    fn ($query) => $query->where(
                        'workspace_id',
                        $workspace?->id
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
                    $this->filled('starts_at'),
                    [
                        'after_or_equal:starts_at',
                    ]
                ),
            ],
        ];
    }
}
