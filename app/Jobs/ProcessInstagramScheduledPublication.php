<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InstagramPublication;
use App\Services\InstagramPublishingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessInstagramScheduledPublication implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var int[]
     */
    public array $backoff = [
        30,
        120,
        300,
    ];

    public function __construct(
        public readonly int $publicationId,
    ) {
    }

    public function handle(
        InstagramPublishingService $publishingService,
    ): void {
        $publication = InstagramPublication::query()
            ->find($this->publicationId);

        if ($publication === null) {
            return;
        }

        if (in_array(
            $publication->status,
            [
                'published',
                'failed',
            ],
            true,
        )) {
            return;
        }

        $publishingService->processScheduledPublication(
            $publication,
        );
    }
}
