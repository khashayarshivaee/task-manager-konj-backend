<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredServer;
use App\Models\MonitoredServerSample;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MonitoredServerHistoryController extends Controller
{
    public function __invoke(
        Request $request,
        MonitoredServer $server,
    ): JsonResponse {
        abort_unless($server->is_enabled, 404);

        $range = $request->query('range', '24h');

        // Range duration in minutes and bucket size in seconds.
        $ranges = [
            '24h' => [1440, 300],
            '7d'  => [10080, 3600],
            '30d' => [43200, 21600],
        ];

        if (
            !is_string($range) ||
            !isset($ranges[$range])
        ) {
            return response()->json([
                'message' => 'Invalid history range.',
            ], 422);
        }

        [$minutes, $bucketSeconds] = $ranges[$range];

        $now = CarbonImmutable::now('UTC');
        $from = $now->subMinutes($minutes);

        $samples = MonitoredServerSample::query()
            ->where('monitored_server_id', $server->id)
            ->whereBetween('measured_at', [
                $from->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
            ])
            ->orderBy('measured_at')
            ->get([
                'cpu_percent',
                'memory_percent',
                'disk_percent',
                'measured_at',
            ]);

        $buckets = [];

        $peakCpu = null;
        $peakCpuAt = null;

        foreach ($samples as $sample) {
            $timestamp = $sample->measured_at->timestamp;

            $bucket = intdiv(
                    $timestamp,
                    $bucketSeconds,
                ) * $bucketSeconds;

            if (!isset($buckets[$bucket])) {
                $buckets[$bucket] = [
                    'count' => 0,
                    'cpu_sum' => 0,
                    'cpu_max' => 0,
                    'memory_sum' => 0,
                    'disk_sum' => 0,
                ];
            }

            $buckets[$bucket]['count']++;

            $buckets[$bucket]['cpu_sum'] +=
                $sample->cpu_percent;

            $buckets[$bucket]['cpu_max'] = max(
                $buckets[$bucket]['cpu_max'],
                $sample->cpu_percent,
            );

            $buckets[$bucket]['memory_sum'] +=
                $sample->memory_percent;

            $buckets[$bucket]['disk_sum'] +=
                $sample->disk_percent;

            if (
                $peakCpu === null ||
                $sample->cpu_percent > $peakCpu
            ) {
                $peakCpu = $sample->cpu_percent;

                $peakCpuAt = $sample->measured_at
                    ->utc()
                    ->toIso8601String();
            }
        }

        $points = [];

        foreach ($buckets as $timestamp => $bucket) {
            $count = $bucket['count'];

            $points[] = [
                'time' => gmdate(
                    'Y-m-d\TH:i:s\Z',
                    (int) $timestamp,
                ),

                'cpu_avg' => round(
                    $bucket['cpu_sum'] / $count,
                    1,
                ),

                'cpu_max' => round(
                    $bucket['cpu_max'],
                    1,
                ),

                'memory_avg' => round(
                    $bucket['memory_sum'] / $count,
                    1,
                ),

                'disk_avg' => round(
                    $bucket['disk_sum'] / $count,
                    1,
                ),
            ];
        }

        return response()->json([
            'status' => $samples->isEmpty()
                ? 'pending'
                : 'completed',

            'server' => [
                'id' => $server->id,
                'slug' => $server->slug,
                'name' => $server->name,
            ],

            'range' => $range,
            'interval_seconds' => $bucketSeconds,
            'sample_count' => $samples->count(),

            'summary' => [
                'peak_cpu_percent' => $peakCpu,
                'peak_cpu_at' => $peakCpuAt,
            ],

            'data' => $points,
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
