<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\XrayDestinationLogReader;
use PHPUnit\Framework\TestCase;

class XrayDestinationLogReaderTest extends TestCase
{
    public function test_it_parses_xray_access_log_line(): void
    {
        $reader = new XrayDestinationLogReader();

        $result = $reader->parseLine(
            '2026/08/17 18:43:05.268627 from 188.229.87.79:6834 accepted tcp:i.instagram.com:443 [vless-reality >> direct] email: bootstrap@task-manager',
        );

        $this->assertNotNull($result);

        $this->assertSame(
            '188.229.87.79',
            $result['client_ip'],
        );

        $this->assertSame(
            6834,
            $result['client_port'],
        );

        $this->assertSame(
            'tcp',
            $result['protocol'],
        );

        $this->assertSame(
            'i.instagram.com',
            $result['destination'],
        );

        $this->assertSame(
            443,
            $result['destination_port'],
        );

        $this->assertSame(
            'bootstrap@task-manager',
            $result['xray_email'],
        );

        $this->assertSame(
            '2026-08-17 18:43:05.268627',
            $result['occurred_at']->format(
                'Y-m-d H:i:s.u',
            ),
        );
    }

    public function test_it_returns_null_for_empty_line(): void
    {
        $reader = new XrayDestinationLogReader();

        $this->assertNull(
            $reader->parseLine(''),
        );
    }

    public function test_it_returns_null_for_unrelated_log_line(): void
    {
        $reader = new XrayDestinationLogReader();

        $this->assertNull(
            $reader->parseLine(
                '2026/08/17 18:43:05 warning something happened',
            ),
        );
    }

    public function test_it_normalizes_protocol_and_destination_to_lowercase(): void
    {
        $reader = new XrayDestinationLogReader();

        $result = $reader->parseLine(
            '2026/08/17 18:43:05.268627 from 188.229.87.79:6834 accepted TCP:WWW.INSTAGRAM.COM:443 [vless-reality >> direct] email: bootstrap@task-manager',
        );

        $this->assertNotNull($result);

        $this->assertSame(
            'tcp',
            $result['protocol'],
        );

        $this->assertSame(
            'www.instagram.com',
            $result['destination'],
        );
    }
}
