<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VpnLogCursor;
use App\Services\XrayDestinationIngestService;
use App\Services\XrayRemoteDestinationReaderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class XrayIngestRemoteDestinations extends Command
{
    protected $signature =
        'vpn:xray-ingest-remote-destinations';

    protected $description =
        'Ingest Xray destinations from the Germany server.';

    private const SOURCE = 'xray-remote-germany';

    private const LOG_PATH =
        'ssh://deploy@116.202.1.136/var/log/xray-konj-v2/access.log';

    public function handle(
        XrayRemoteDestinationReaderService $reader,
        XrayDestinationIngestService $ingestService,
    ): int {
        try {
            $cursor = VpnLogCursor::query()
                ->firstOrCreate(
                    [
                        'source' => self::SOURCE,
                    ],
                    [
                        'path' => self::LOG_PATH,
                        'offset' => 0,
                        'inode' => null,
                    ],
                );

            $previousOffset = (int) $cursor->offset;
            $previousInode = (int) ($cursor->inode ?? 0);

            $chunk = $reader->read(
                $previousOffset,
                $previousInode,
            );

            $lines = [];

            if ($chunk['data'] !== '') {
                $lines = explode(
                    "\n",
                    $chunk['data'],
                );

                // The reader returns complete lines only.
                array_pop($lines);
            }

            $counts = DB::transaction(
                function () use (
                    $cursor,
                    $previousOffset,
                    $previousInode,
                    $chunk,
                    $lines,
                    $ingestService,
                ): array {
                    $lockedCursor = VpnLogCursor::query()
                        ->whereKey($cursor->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Prevent concurrent collectors from
                    // processing the same log chunk.
                    if (
                        (int) $lockedCursor->offset
                        !== $previousOffset
                        ||
                        (int) ($lockedCursor->inode ?? 0)
                        !== $previousInode
                    ) {
                        throw new RuntimeException(
                            'Xray destination cursor changed during collection.',
                        );
                    }

                    $processed = 0;
                    $ingested = 0;
                    $ignored = 0;

                    foreach ($lines as $line) {
                        $processed++;

                        if ($ingestService->ingestLine($line)) {
                            $ingested++;
                        } else {
                            $ignored++;
                        }
                    }

                    // Save the cursor in the same transaction
                    // as all destination updates.
                    $lockedCursor->forceFill([
                        'path' => self::LOG_PATH,
                        'inode' => $chunk['inode'],
                        'offset' => $chunk['next_offset'],
                        'last_processed_at' => now(),
                    ])->save();

                    return [
                        'processed' => $processed,
                        'ingested' => $ingested,
                        'ignored' => $ignored,
                    ];
                },
                3,
            );

            $this->info(sprintf(
                'Processed: %d | Ingested: %d | Ignored: %d | Offset: %d | Reset: %s',
                $counts['processed'],
                $counts['ingested'],
                $counts['ignored'],
                $chunk['next_offset'],
                $chunk['reset'] ? 'yes' : 'no',
            ));

            return self::SUCCESS;

        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'Remote Xray destinations collection failed.',
            );

            return self::FAILURE;
        }
    }
}
