<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\User;
use App\Models\VpnLogCursor;
use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class XrayIngestRemoteDestinationsTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND =
        'vpn:xray-ingest-remote-destinations';

    private const SOURCE =
        'xray-remote-germany';

    private const EMAIL =
        'vpn-remote-test@example.test';

    private const INODE = 649719;

    public function test_it_ingests_without_duplicates(): void
    {
        $this->createVpnUser();

        $line = $this->logLine() . "\n";
        $offset = strlen($line);

        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result(
                    output: $this->response(
                        $line,
                        0,
                        self::INODE,
                    ),
                ))
                ->push(Process::result(
                    output: $this->response(
                        '',
                        $offset,
                        self::INODE,
                    ),
                )),
        ]);

        $this->assertSame(
            0,
            Artisan::call(self::COMMAND),
        );

        $this->assertDatabaseHas(
            'vpn_user_destinations',
            [
                'destination' => 'www.instagram.com',
                'connection_count' => 1,
            ],
        );

        $this->assertDatabaseHas(
            'vpn_log_cursors',
            [
                'source' => self::SOURCE,
                'offset' => $offset,
                'inode' => self::INODE,
            ],
        );

        // Reading again should not duplicate records.
        $this->assertSame(
            0,
            Artisan::call(self::COMMAND),
        );

        $this->assertDatabaseHas(
            'vpn_user_destinations',
            [
                'destination' => 'www.instagram.com',
                'connection_count' => 1,
            ],
        );
    }

    public function test_it_preserves_cursor_when_ssh_fails(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'SSH unavailable',
                exitCode: 255,
            ),
        ]);

        $this->assertSame(
            1,
            Artisan::call(self::COMMAND),
        );

        $this->assertDatabaseHas(
            'vpn_log_cursors',
            [
                'source' => self::SOURCE,
                'offset' => 0,
            ],
        );

        $this->assertDatabaseCount(
            'vpn_user_destinations',
            0,
        );
    }

    public function test_it_handles_remote_log_rotation(): void
    {
        $this->createVpnUser();

        VpnLogCursor::query()->create([
            'source' => self::SOURCE,
            'path' => 'ssh://old-log',
            'offset' => 5000,
            'inode' => 123456,
        ]);

        $line = $this->logLine() . "\n";

        Process::fake([
            '*' => Process::result(
                output: $this->response(
                    $line,
                    0,
                    self::INODE,
                    true,
                ),
            ),
        ]);

        $this->assertSame(
            0,
            Artisan::call(self::COMMAND),
        );

        $this->assertDatabaseHas(
            'vpn_log_cursors',
            [
                'source' => self::SOURCE,
                'inode' => self::INODE,
                'offset' => strlen($line),
            ],
        );

        $this->assertDatabaseHas(
            'vpn_user_destinations',
            [
                'destination' => 'www.instagram.com',
                'connection_count' => 1,
            ],
        );
    }

    private function createVpnUser(): void
    {
        $user = User::factory()->create();

        VpnUser::query()->create([
            'name' => 'Remote Test User',
            'uuid' => '11111111-2222-4333-8444-555555555555',
            'xray_email' => self::EMAIL,
            'is_active' => true,
            'flow' => '',
            'created_by' => $user->id,
        ]);
    }

    private function logLine(): string
    {
        return '2026/11/01 09:03:11.978080 '
            . 'from 192.0.2.10:12345 '
            . 'accepted tcp:www.instagram.com:443 '
            . '[vless-ws-edge >> direct] '
            . 'email: ' . self::EMAIL;
    }

    private function response(
        string $data,
        int $startOffset,
        int $inode,
        bool $reset = false,
    ): string {
        return json_encode([
            'inode' => $inode,
            'size' => $startOffset + strlen($data),
            'start_offset' => $startOffset,
            'next_offset' =>
                $startOffset + strlen($data),
            'reset' => $reset,
            'data_base64' => base64_encode($data),
        ], JSON_THROW_ON_ERROR);
    }
}
