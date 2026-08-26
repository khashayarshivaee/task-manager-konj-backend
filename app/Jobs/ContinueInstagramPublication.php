<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\InstagramPublication;
use App\Services\InstagramPublishingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ContinueInstagramPublication implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    public int $timeout = 120;

    public function __construct(
        public readonly int $publicationId,
    ) {}

    public function backoff(): array
    {
        return [
            30,
            60,
            120,
            300,
        ];
    }

    public function handle(
        InstagramPublishingService $publishingService,
    ): void {
        $publication = InstagramPublication::query()
            ->with('instagramAccount')
            ->find($this->publicationId);

        if ($publication === null) {
            return;
        }

        if (
            in_array(
                $publication->status,
                [
                    'published',
                    'failed',
                    'cancelled',
                ],
                true,
            )
        ) {
            return;
        }

        if ($publication->status !== 'processing') {
            return;
        }

        $account = $publication->instagramAccount;

        if (
            $account === null ||
            !$account->is_active
        ) {
            return;
        }

        $publication =
            $publishingService
                ->continuePublication(
                    $publication,
                    $account,
                );

        if ($publication->status === 'processing') {
            $this->release(30);
        }
    }
}
