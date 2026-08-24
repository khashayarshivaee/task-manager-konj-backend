<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstagramPublication extends Model
{
    protected $fillable = [
        'workspace_id',
        'instagram_account_id',
        'type',
        'caption',
        'staging_path',
        'container_id',
        'media_id',
        'status',
        'error_message',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(
            InstagramAccount::class
        );
    }
}
