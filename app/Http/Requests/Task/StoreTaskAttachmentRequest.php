<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAttachmentRequest extends FormRequest
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
            'images' => [
                'required',
                'array',
                'min:1',
                'max:5',
            ],

            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.max' =>
                'You can upload up to 5 images at once.',

            'images.*.image' =>
                'Every attachment must be a valid image.',

            'images.*.mimes' =>
                'Images must be JPG, PNG, or WebP.',

            'images.*.max' =>
                'Each image cannot exceed 10 MB.',
        ];
    }
}
