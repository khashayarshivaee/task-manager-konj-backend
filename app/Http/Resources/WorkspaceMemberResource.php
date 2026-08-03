<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => (int) $this->id,

            'workspace_id' =>
                (int) $this->workspace_id,

            'user_id' => (int) $this->user_id,

            'role' => $this->role->value,

            'joined_at' =>
                $this->joined_at?->toISOString(),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),

            'user' => [
                'id' => (int) $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,

                'avatar_path' =>
                    $this->user->avatar_path,

                'is_active' =>
                    (bool) $this->user->is_active,
            ],
        ];
    }
}
