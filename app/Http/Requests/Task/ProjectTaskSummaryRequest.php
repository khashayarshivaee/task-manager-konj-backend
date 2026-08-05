<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectTaskSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $period = strtolower(
            trim(
                (string) $this->input(
                    'period',
                    'all'
                )
            )
        );

        $this->merge([
            'period' =>
                $period !== ''
                    ? $period
                    : 'all',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => [
                'required',

                Rule::in([
                    'all',
                    'this_week',
                    'this_month',
                    'this_year',
                ]),
            ],
        ];
    }
}
