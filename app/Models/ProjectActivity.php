<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectActivityType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ProjectActivitySubjectType;
class ProjectActivity extends Model
{
    protected $fillable = [
        'project_id',
        'actor_id',
        'type',
        'subject_type',
        'subject_id',
        'subject_label',
        'metadata',
    ];

  protected function casts(): array
  {
      return [
          'type' =>
              ProjectActivityType::class,

          'subject_type' =>
              ProjectActivitySubjectType::class,

          'metadata' => 'array',
      ];
  }

    public function project(): BelongsTo
    {
        return $this->belongsTo(
            Project::class
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_id',
        );
    }
}
