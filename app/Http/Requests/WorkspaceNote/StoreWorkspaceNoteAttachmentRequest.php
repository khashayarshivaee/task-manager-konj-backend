<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkspaceNote;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceNoteAttachmentRequest extends FormRequest
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
            'images' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],

            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
            ],
        ];
    }
}
