<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a text message with the school's Telegram bot (TELEGRAM_BOT_TOKEN in .env).
 * Used for parent messages and for password reset codes.
 */
class Telegram
{
    public function isLive(): bool
    {
        return filled(config('services.telegram.token'));
    }

    /** Returns null when Telegram accepted the message, otherwise a short reason. Never throws. */
    public function send(string $chatId, string $text): ?string
    {
        if (! $this->isLive()) {
            return 'Telegram is not connected.';
        }

        try {
            $response = Http::timeout(8)->asForm()->post(
                'https://api.telegram.org/bot'.config('services.telegram.token').'/sendMessage',
                ['chat_id' => $chatId, 'text' => $text],
            );
            if ($response->successful() && $response->json('ok')) {
                return null;
            }

            return mb_substr((string) ($response->json('description') ?? 'HTTP '.$response->status()), 0, 250);
        } catch (Throwable $e) {
            Log::warning('Telegram message failed', ['error' => $e->getMessage()]);

            return __('No connection to Telegram. Try again later.');
        }
    }
}
