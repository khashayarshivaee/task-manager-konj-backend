<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\VpnUser;
use App\Models\VpnUserDestination;
use App\Services\XrayDestinationIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XrayDestinationIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ingests_destination_for_known_vpn_user(): void
    {
        $user = User::factory()->create();

        $vpnUser = VpnUser::query()->create([
            'name' => 'Khashayar iPhone',
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'xray_email' => 'vpn-iphone@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $user->id,
        ]);

        /** @var XrayDestinationIngestService $service */
        $service = app(
            XrayDestinationIngestService::class,
        );

        $result = $service->ingestLine(
            '2026/08/17 18:43:05.268627 from 188.229.87.79:6834 accepted tcp:i.instagram.com:443 [vless-reality >> direct] email: vpn-iphone@task-manager.local',
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas(
            'vpn_user_destinations',
            [
                'vpn_user_id' =>
                    $vpnUser->id,

                'destination' =>
                    'i.instagram.com',

                'connection_count' =>
                    1,
            ],
        );

        $destination = VpnUserDestination::query()
            ->firstOrFail();

        $this->assertSame(
            '2026-08-17 18:43:05',
            $destination
                ->first_seen_at
                ?->format('Y-m-d H:i:s'),
        );

        $this->assertSame(
            '2026-08-17 18:43:05',
            $destination
                ->last_seen_at
                ?->format('Y-m-d H:i:s'),
        );
    }

    public function test_it_increments_existing_destination(): void
    {
        $user = User::factory()->create();

        $vpnUser = VpnUser::query()->create([
            'name' => 'Khashayar iPhone',
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'xray_email' => 'vpn-iphone@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $user->id,
        ]);

        /** @var XrayDestinationIngestService $service */
        $service = app(
            XrayDestinationIngestService::class,
        );

        $service->ingestLine(
            '2026/08/17 18:43:05.268627 from 188.229.87.79:6834 accepted tcp:i.instagram.com:443 [vless-reality >> direct] email: vpn-iphone@task-manager.local',
        );

        $service->ingestLine(
            '2026/08/17 18:44:10.100000 from 188.229.87.79:6840 accepted tcp:i.instagram.com:443 [vless-reality >> direct] email: vpn-iphone@task-manager.local',
        );

        $destination = VpnUserDestination::query()
            ->where(
                'vpn_user_id',
                $vpnUser->id,
            )
            ->where(
                'destination',
                'i.instagram.com',
            )
            ->firstOrFail();

        $this->assertSame(
            2,
            $destination->connection_count,
        );

        $this->assertSame(
            '2026-08-17 18:43:05',
            $destination
                ->first_seen_at
                ?->format('Y-m-d H:i:s'),
        );

        $this->assertSame(
            '2026-08-17 18:44:10',
            $destination
                ->last_seen_at
                ?->format('Y-m-d H:i:s'),
        );
    }

    public function test_it_ignores_unknown_xray_user(): void
    {
        /** @var XrayDestinationIngestService $service */
        $service = app(
            XrayDestinationIngestService::class,
        );

        $result = $service->ingestLine(
            '2026/08/17 18:43:05.268627 from 188.229.87.79:6834 accepted tcp:i.instagram.com:443 [vless-reality >> direct] email: bootstrap@task-manager',
        );

        $this->assertFalse($result);

        $this->assertDatabaseCount(
            'vpn_user_destinations',
            0,
        );
    }

    public function test_it_ignores_unrelated_log_line(): void
    {
        /** @var XrayDestinationIngestService $service */
        $service = app(
            XrayDestinationIngestService::class,
        );

        $result = $service->ingestLine(
            '2026/08/17 18:43:05 warning something happened',
        );

        $this->assertFalse($result);

        $this->assertDatabaseCount(
            'vpn_user_destinations',
            0,
        );
    }

    public function test_it_normalizes_destination_before_saving(): void
    {
        $user = User::factory()->create();

        $vpnUser = VpnUser::query()->create([
            'name' => 'Khashayar iPhone',
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'xray_email' => 'vpn-iphone@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $user->id,
        ]);

        /** @var XrayDestinationIngestService $service */
        $service = app(
            XrayDestinationIngestService::class,
        );

        $service->ingestLine(
            '2026/08/17 18:43:05.268627 from 188.229.87.79:6834 accepted tcp:WWW.INSTAGRAM.COM.:443 [vless-reality >> direct] email: vpn-iphone@task-manager.local',
        );

        $this->assertDatabaseHas(
            'vpn_user_destinations',
            [
                'vpn_user_id' =>
                    $vpnUser->id,

                'destination' =>
                    'www.instagram.com',
            ],
        );
    }
}
