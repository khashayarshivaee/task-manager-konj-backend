<?php

declare(strict_types=1);

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

final class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => trim(
                (string) $this->input('q', '')
            ),

            'limit' => $this->input(
                'limit',
                6,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => [
                'required',
                'string',
                'min:2',
                'max:120',
            ],

            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:10',
            ],
        ];
    }
}
