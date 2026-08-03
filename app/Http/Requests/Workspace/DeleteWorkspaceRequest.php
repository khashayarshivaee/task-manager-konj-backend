<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use App\Models\Workspace;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeleteWorkspaceRequest extends FormRequest
{
    /**
     * Authorization is handled by WorkspacePolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize incoming data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmation_name' => trim(
                (string) $this->input(
                    'confirmation_name'
                )
            ),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'confirmation_name' => [
                'required',
                'string',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    $workspace = $this->route(
                        'workspace'
                    );

                    if (
                        ! $workspace instanceof Workspace
                        || ! hash_equals(
                            $workspace->name,
                            (string) $value
                        )
                    ) {
                        $fail(
                            'The workspace name confirmation does not match.'
                        );
                    }
                },
            ],
        ];
    }
}
