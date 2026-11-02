<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MonitoredServer;
use Illuminate\Database\Seeder;

final class MonitoredServerSeeder extends Seeder
{
    public function run(): void
    {
        MonitoredServer::query()->firstOrCreate(
            ['slug' => 'iran'],
            [
                'name' => 'Iran Server',
                'region' => 'Iran',
                'driver' => 'local',
                'is_enabled' => true,
                'sort_order' => 0,
            ],
        );

        MonitoredServer::query()->firstOrCreate(
            ['slug' => 'germany'],
            [
                'name' => 'Germany Server',
                'region' => 'Germany',
                'driver' => 'ssh',
                'is_enabled' => true,
                'sort_order' => 1,
            ],
        );


        MonitoredServer::query()->firstOrCreate(
            ['slug' => 'company'],
            [
                'name' => 'Company Main Server',
                'region' => null,
                'driver' => 'ssh',
                'is_enabled' => true,
                'sort_order' => 2,
            ],
        );

    }
}
