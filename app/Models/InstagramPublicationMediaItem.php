<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramPublicationMediaItem extends Model
{
    protected $fillable = [
        'instagram_publication_id',
        'media_kind',
        'staging_path',
        'position',
        'container_id',
        'container_status',
        'error_message',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(
            InstagramPublication::class,
            'instagram_publication_id',
        );
    }
}
