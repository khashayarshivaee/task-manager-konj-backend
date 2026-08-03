<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TaskCommentAttachment
 */
class TaskCommentAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        return [
            'id' => $this->id,

            'comment_id' =>
                $this->comment_id,

            'original_name' =>
                $this->original_name,

            'mime_type' =>
                $this->mime_type,

            'size' => $this->size,

            'uploader' =>
                $this->whenLoaded(
                    'uploader',
                    fn (): ?array => $this->uploader
                        ? [
                            'id' =>
                                $this->uploader->id,

                            'name' =>
                                $this->uploader->name,
                        ]
                        : null,
                ),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),
        ];
    }
}
