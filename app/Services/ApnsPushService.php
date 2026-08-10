<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushDevice;
use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;
use RuntimeException;

final class ApnsPushService
{
    /**
     * @param array<string, mixed> $data
     */
    public function sendToDevice(
        PushDevice $device,
        string $title,
        string $body,
        array $data = [],
        ?int $badge = null,
    ): void {
        if (
            !$device->is_active ||
            $device->platform !== 'ios'
        ) {
            return;
        }

        $authProvider =
            AuthProvider\Token::create([
                'key_id' =>
                    $this->config(
                        'key_id',
                    ),

                'team_id' =>
                    $this->config(
                        'team_id',
                    ),

                'app_bundle_id' =>
                    $this->config(
                        'bundle_id',
                    ),

                'private_key_path' =>
                    $this->config(
                        'private_key_path',
                    ),

                'private_key_secret' =>
                    null,
            ]);

        $alert = Alert::create()
            ->setTitle($title)
            ->setBody($body);

        $payload =
            Payload::create()
                ->setAlert($alert);

        $payload->setSound(
            'default',
        );

        if ($badge !== null) {
            $payload->setBadge(
                $badge,
            );
        }

        foreach (
            $data as $key => $value
        ) {
            $payload->setCustomValue(
                (string) $key,
                $value,
            );
        }

        $notification =
            new Notification(
                $payload,
                $device->token,
            );

        /*
         * development => APNs Sandbox
         * production  => APNs Production
         */
        $production =
            $device->environment ===
            'production';

        $client =
            new Client(
                $authProvider,
                $production,
            );

        $client->addNotification(
            $notification,
        );

        $responses =
            $client->push();

        foreach (
            $responses as $response
        ) {
            $statusCode =
                $response
                    ->getStatusCode();

            if ($statusCode === 200) {
                continue;
            }

            /*
             * APNs 410 means this token is
             * no longer valid for the topic.
             */
            if ($statusCode === 410) {
                $device->update([
                    'is_active' => false,
                ]);
            }

            throw new RuntimeException(
                sprintf(
                    'APNs error %d: %s (%s)',
                    $statusCode,
                    $response
                        ->getReasonPhrase(),
                    $response
                        ->getErrorReason()
                        ?? 'unknown',
                ),
            );
        }
    }

    private function config(
        string $key,
    ): string {
        $value =
            config(
                "services.apns.{$key}",
            );

        if (
            !is_string($value) ||
            trim($value) === ''
        ) {
            throw new RuntimeException(
                "Missing APNs configuration: {$key}",
            );
        }

        return $value;
    }
}
