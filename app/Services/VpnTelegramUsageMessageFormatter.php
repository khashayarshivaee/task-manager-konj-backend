<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class VpnTelegramUsageMessageFormatter
{
    private const BYTES_PER_GB =
        1024 * 1024 * 1024;

    public function format(
        string $vpnUserName,
        int $thresholdGb,
        int $totalBytes,
    ): string {
        if ($thresholdGb <= 0 || $totalBytes < 0) {
            throw new InvalidArgumentException(
                'Invalid VPN usage values.',
            );
        }

        /*
         * Telegram messages use HTML
         * formatting. Escape user-provided
         * values to prevent HTML injection.
         */
        $name = htmlspecialchars(
            trim($vpnUserName) ?: 'there',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $usageGb = number_format(
            $totalBytes / self::BYTES_PER_GB,
            2,
            '.',
            '',
        );

        /*
         * Friendly 3 GB notification.
         */
        if ($thresholdGb === 3) {
            return implode("\n", [
                '📊 <b>Daily Usage Update</b>',
                '',
                "Hi {$name},",
                '',
                "You've reached <b>3 GB</b> of VPN usage today.",
                '',
                'Just a friendly heads-up from KONJ. Everything is running smoothly! ✨',
                '',
                "<b>Today's usage:</b> {$usageGb} GB",
                '',
                '— KONJ TEAM 🧡',
            ]);
        }

        /*
         * Friendly 5 GB notification.
         */
        if ($thresholdGb === 5) {
            return implode("\n", [
                '🚀 <b>Daily Usage Update</b>',
                '',
                "Hi {$name},",
                '',
                'Your VPN usage has now reached <b>5 GB</b> today.',
                '',
                'Looks like a productive day online! 😊',
                '',
                "No action needed — we're just keeping you informed.",
                '',
                "<b>Today's usage:</b> {$usageGb} GB",
                '',
                '— KONJ TEAM 🧡',
            ]);
        }

        /*
         * Fallback for future configurable
         * usage thresholds such as 6 or 8 GB.
         */
        return implode("\n", [
            '📊 <b>Daily Usage Update</b>',
            '',
            "Hi {$name},",
            '',
            "Your VPN usage has reached <b>{$thresholdGb} GB</b> today.",
            '',
            'Just a friendly update from KONJ. ✨',
            '',
            "<b>Today's usage:</b> {$usageGb} GB",
            '',
            '— KONJ TEAM 🧡',
        ]);
    }
}
