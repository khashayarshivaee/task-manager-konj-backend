<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    /**
     * Determine whether the user may
     * make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for changing
     * only the task status.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::enum(TaskStatus::class),
            ],
        ];
    }
}
