<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(
                trim((string) $this->input('email'))
            ),

            'role' => trim(
                (string) $this->input('role')
            ),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::exists('users', 'email')
                    ->where(
                        static fn (
                            Builder $query
                        ): Builder => $query->where(
                            'is_active',
                            true
                        )
                    ),
            ],

            'role' => [
                'required',
                'string',
                Rule::in([
                    WorkspaceRole::Admin->value,
                    WorkspaceRole::Member->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' =>
                'No active user was found with this email address.',

            'role.in' =>
                'The selected workspace role is invalid.',
        ];
    }
}
