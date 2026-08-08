<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
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
        $workspaceId = (int) $this->route(
            'workspace'
        )?->id;

        $projectId = (int) $this->route(
            'project'
        )?->id;

        return [
            'user_id' => [
                'required',
                'integer',

                Rule::exists(
                    'users',
                    'id'
                )->where(
                    static fn (
                        Builder $query
                    ): Builder => $query->where(
                        'is_active',
                        true
                    )
                ),

                Rule::exists(
                    'workspace_memberships',
                    'user_id'
                )->where(
                    static fn (
                        Builder $query
                    ): Builder => $query->where(
                        'workspace_id',
                        $workspaceId
                    )
                ),

                Rule::unique(
                    'project_memberships',
                    'user_id'
                )->where(
                    static fn (
                        Builder $query
                    ): Builder => $query->where(
                        'project_id',
                        $projectId
                    )
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' =>
                'The selected user must be an active member of this workspace.',

            'user_id.unique' =>
                'This user is already a member of the project.',
        ];
    }
}
