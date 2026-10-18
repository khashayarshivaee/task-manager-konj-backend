<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TelegramBotService
{
    /**
     * Send a text message to a Telegram user.
     */
    public function sendMessage(
        int $chatId,
        string $message,
    ): void {
        $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    /**
     * Send a Telegram sticker using
     * an existing Telegram file_id.
     */
    public function sendSticker(
        int $chatId,
        string $stickerFileId,
    ): void {
        if (trim($stickerFileId) === '') {
            return;
        }

        $this->request('sendSticker', [
            'chat_id' => $chatId,
            'sticker' => $stickerFileId,
        ]);
    }

    /**
     * Make an authenticated request
     * to Telegram Bot API.
     */
    private function request(
        string $method,
        array $payload,
    ): void {
        $token = config(
            'services.telegram.bot_token',
        );

        if (
            !is_string($token) ||
            trim($token) === ''
        ) {
            throw new RuntimeException(
                'Telegram bot token is not configured.',
            );
        }

        $response = Http::timeout(15)
            ->asJson()
            ->post(
                sprintf(
                    'https://api.telegram.org/bot%s/%s',
                    $token,
                    $method,
                ),
                $payload,
            );

        if (
            !$response->successful() ||
            $response->json('ok') !== true
        ) {
            throw new RuntimeException(
                sprintf(
                    'Telegram API request failed: %s.',
                    $method,
                ),
            );
        }
    }
}
