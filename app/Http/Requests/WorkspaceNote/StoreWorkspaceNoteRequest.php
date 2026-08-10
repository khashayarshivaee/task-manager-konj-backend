<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkspaceNote;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:180',
            ],

            'content' => [
                'nullable',
                'string',
            ],

            'is_pinned' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge([
                'title' => trim(
                    (string) $this->input('title')
                ),
            ]);
        }
    }
}
