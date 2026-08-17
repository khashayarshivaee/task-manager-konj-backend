<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\VpnUserDestination;
class VpnUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uuid',
        'xray_email',
        'is_active',
        'flow',
        'created_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by',
        );
    }

    /**
     * @param Builder<VpnUser> $query
     * @return Builder<VpnUser>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(
            VpnUserDestination::class,
        );
    }
}
