<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $body = trim(
                (string) $this->input('body'),
            );

            $this->merge([
                'body' => $body !== ''
                    ? $body
                    : null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }
}
