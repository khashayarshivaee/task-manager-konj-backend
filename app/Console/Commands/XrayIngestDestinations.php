<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VpnLogCursor;
use App\Services\XrayDestinationIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class XrayIngestDestinations extends Command
{
    protected $signature = 'vpn:xray-ingest-destinations
        {--file=/var/log/xray/access.log : Xray access log path}
        {--source=xray-access : Cursor source name}';

    protected $description =
        'Ingest new Xray VPN user destinations from the access log.';

    public function handle(
        XrayDestinationIngestService $ingestService,
    ): int {
        $path = (string) $this->option('file');
        $source = (string) $this->option('source');

        if (! is_file($path)) {
            $this->error(
                sprintf(
                    'Xray access log not found: %s',
                    $path,
                ),
            );

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error(
                sprintf(
                    'Xray access log is not readable: %s',
                    $path,
                ),
            );

            return self::FAILURE;
        }

        $stat = stat($path);

        if ($stat === false) {
            $this->error(
                'Unable to inspect Xray access log.',
            );

            return self::FAILURE;
        }

        $currentInode = isset($stat['ino'])
            ? (int) $stat['ino']
            : null;

        $currentSize = isset($stat['size'])
            ? (int) $stat['size']
            : 0;

        $cursor = VpnLogCursor::query()
            ->firstOrCreate(
                [
                    'source' => $source,
                ],
                [
                    'path' => $path,
                    'offset' => 0,
                    'inode' => $currentInode,
                ],
            );

        $startOffset = (int) $cursor->offset;

        /*
         * If the file changed because of log rotation,
         * or the current file became smaller than our
         * stored offset, start from the beginning.
         */
        if (
            $cursor->inode !== null &&
            $currentInode !== null &&
            (int) $cursor->inode !== $currentInode
        ) {
            $startOffset = 0;
        }

        if ($currentSize < $startOffset) {
            $startOffset = 0;
        }

        $handle = fopen(
            $path,
            'rb',
        );

        if ($handle === false) {
            $this->error(
                'Unable to open Xray access log.',
            );

            return self::FAILURE;
        }

        if (
            $startOffset > 0 &&
            fseek(
                $handle,
                $startOffset,
                SEEK_SET,
            ) !== 0
        ) {
            fclose($handle);

            $this->error(
                'Unable to seek to the saved Xray log cursor.',
            );

            return self::FAILURE;
        }

        $processed = 0;
        $ingested = 0;
        $ignored = 0;

        try {
            while (
                ($line = fgets($handle)) !== false
            ) {
                $processed++;

                try {
                    if (
                        $ingestService->ingestLine(
                            $line,
                        )
                    ) {
                        $ingested++;
                    } else {
                        $ignored++;
                    }
                } catch (Throwable $exception) {
                    report($exception);

                    $ignored++;
                }
            }

            $finalOffset = ftell($handle);

            if ($finalOffset === false) {
                throw new \RuntimeException(
                    'Unable to determine final Xray log offset.',
                );
            }

            DB::transaction(
                function () use (
                    $cursor,
                    $path,
                    $currentInode,
                    $finalOffset,
                ): void {
                    $cursor->forceFill([
                        'path' => $path,
                        'offset' => $finalOffset,
                        'inode' => $currentInode,
                        'last_processed_at' => now(),
                    ])->save();
                },
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'Xray destination ingest failed.',
            );

            return self::FAILURE;
        } finally {
            fclose($handle);
        }

        $this->info(
            sprintf(
                'Processed: %d | Ingested: %d | Ignored: %d | Offset: %d',
                $processed,
                $ingested,
                $ignored,
                $cursor->fresh()?->offset ?? 0,
            ),
        );

        return self::SUCCESS;
    }
}
