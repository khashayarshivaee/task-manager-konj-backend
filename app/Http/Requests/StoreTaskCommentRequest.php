<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTaskCommentRequest extends FormRequest
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
            'parent_id' => [
                'nullable',
                'integer',
            ],

            'body' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'images' => [
                'nullable',
                'array',
                'max:5',
            ],

            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'voice' => [
                'nullable',
                'file',
                'max:10240',
                'mimetypes:audio/webm,audio/mp4,video/mp4,audio/mpeg,audio/aac,audio/x-m4a',
            ],

            'voice_duration_ms' => [
                'nullable',
                'integer',
                'min:1',
                'max:180000',
                'required_with:voice',
            ],
        ];
    }

    public function withValidator(
        Validator $validator,
    ): void {
        $validator->after(
            function (
                Validator $validator,
            ): void {
                $body = trim(
                    (string) $this->input(
                        'body',
                        '',
                    ),
                );

                $images = $this->file(
                    'images',
                    [],
                );

                $voice = $this->file(
                    'voice',
                );

                if (
                    $body === '' &&
                    count($images) === 0 &&
                    $voice === null
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'body',
                            'A comment must contain text, at least one image, or a voice message.',
                        );
                }

                $parentId = $this->input(
                    'parent_id',
                );

                if (!$parentId) {
                    return;
                }

                $task = $this->route('task');

                if (!$task instanceof Task) {
                    $validator
                        ->errors()
                        ->add(
                            'parent_id',
                            'The selected parent comment is invalid.',
                        );

                    return;
                }

                $parentExists =
                    TaskComment::query()
                        ->whereKey($parentId)
                        ->where(
                            'task_id',
                            $task->id,
                        )
                        ->exists();

                if (!$parentExists) {
                    $validator
                        ->errors()
                        ->add(
                            'parent_id',
                            'The selected parent comment does not belong to this task.',
                        );
                }
            },
        );
    }
}
