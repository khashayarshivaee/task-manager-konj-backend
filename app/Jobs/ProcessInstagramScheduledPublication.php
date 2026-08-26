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

    public int $tries = 20;

    public int $timeout = 120;

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [
            30,
            60,
            120,
            300,
        ];
    }

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
                'cancelled',
            ],
            true,
        )) {
            return;
        }
        if (
            $publication->status === 'scheduled'
            && $publication->scheduled_at !== null
            && $publication->scheduled_at->isFuture()
        ) {
            $delay = max(
                1,
                now()->diffInSeconds(
                    $publication->scheduled_at,
                )
            );

            $this->release($delay);

            return;
        }

        $publication = $publishingService

            ->processScheduledPublication(
                $publication,
            );

        if ($publication->status === 'processing') {
            $this->release(30);
        }
    }
}
