<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TaskComment
 */
class TaskCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        $isDeleted =
            $this->deleted_at !== null;

        return [
            'id' => $this->id,

            'task_id' =>
                $this->task_id,

            'parent_id' =>
                $this->parent_id,

            'body' => $isDeleted
                ? null
                : $this->body,

            'is_deleted' =>
                $isDeleted,

            'is_edited' =>
                $this->edited_at !== null,

            'author' =>
                $this->whenLoaded(
                    'user',
                    fn (): ?array => $this->user
                        ? [
                            'id' =>
                                $this->user->id,

                            'name' =>
                                $this->user->name,

                            'avatar_path' =>
                                $this->user
                                    ->avatar_path,
                        ]
                        : null,
                ),

            'attachments' =>
                TaskCommentAttachmentResource::collection(
                    $this->whenLoaded(
                        'attachments',
                    ),
                ),

            'voice_message' =>
                $isDeleted
                    ? null
                    : new TaskCommentVoiceMessageResource(
                    $this->whenLoaded(
                        'voiceMessage',
                    ),
                ),

            'replies' =>
                TaskCommentResource::collection(
                    $this->whenLoaded(
                        'repliesRecursive',
                    ),
                ),

            'edited_at' =>
                $this->edited_at
                    ?->toISOString(),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}
