<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VpnUser;
use App\Services\XrayManagerService;
use Illuminate\Console\Command;
use RuntimeException;

class XrayResyncVpnUsers extends Command
{
    protected $signature = 'vpn:xray-resync';

    protected $description =
        'Restore active Task Manager VPN users into the Xray runtime.';

    public function handle(
        XrayManagerService $xrayManagerService,
    ): int {
        try {
            $xrayEmails = $xrayManagerService
                ->getInboundUserEmails();
        } catch (RuntimeException $exception) {
            report($exception);

            $this->error(
                'Unable to read current Xray users.',
            );

            return self::FAILURE;
        }

        $existingEmails = array_fill_keys(
            $xrayEmails,
            true,
        );

        $vpnUsers = VpnUser::query()
            ->active()
            ->orderBy('id')
            ->get();

        $added = 0;
        $alreadyPresent = 0;
        $failed = 0;

        foreach ($vpnUsers as $vpnUser) {
            if (isset(
                $existingEmails[$vpnUser->xray_email],
            )) {
                $alreadyPresent++;

                continue;
            }

            try {
                $xrayManagerService->addUser(
                    $vpnUser,
                );

                $existingEmails[
                $vpnUser->xray_email
                ] = true;

                $added++;
            } catch (RuntimeException $exception) {
                report($exception);

                $failed++;

                $this->warn(
                    sprintf(
                        'Unable to restore VPN user #%d (%s).',
                        $vpnUser->id,
                        $vpnUser->name,
                    ),
                );
            }
        }

        $this->info(
            sprintf(
                'Xray VPN resync completed: %d added, %d already present, %d failed.',
                $added,
                $alreadyPresent,
                $failed,
            ),
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
