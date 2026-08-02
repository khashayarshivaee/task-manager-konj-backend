<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Authorization is handled by WorkspacePolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare input values for validation.
     */
    protected function prepareForValidation(): void
    {
        $description = trim(
            (string) $this->input('description', '')
        );

        $color = trim(
            (string) $this->input('color', '')
        );

        $startsAt = trim(
            (string) $this->input('starts_at', '')
        );

        $dueAt = trim(
            (string) $this->input('due_at', '')
        );

        $this->merge([
            'name' => trim(
                (string) $this->input('name')
            ),

            'description' => $description !== ''
                ? $description
                : null,

            'color' => $color !== ''
                ? strtolower($color)
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
     * Get the validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'status' => [
                'required',
                Rule::enum(ProjectStatus::class),
            ],

            'color' => [
                'nullable',
                'string',
                'regex:/^#[0-9a-f]{6}$/',
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
