<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VpnLogCursor extends Model
{
    protected $fillable = [
        'source',
        'path',
        'offset',
        'inode',
        'last_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'offset' => 'integer',
            'inode' => 'integer',
            'last_processed_at' => 'datetime',
        ];
    }
}
