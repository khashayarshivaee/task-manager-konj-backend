<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class ListProjectActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' =>
                $this->input(
                    'page',
                    1
                ),

            'per_page' =>
                $this->input(
                    'per_page',
                    20
                ),
        ]);
    }
}
