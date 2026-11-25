<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to the school's Telegram bot (TELEGRAM_BOT_TOKEN in .env).
 * Used for parent messages (with answer buttons under absence alerts) and for password reset codes.
 * Button taps come back through the webhook (TelegramWebhookController).
 */
class Telegram
{
    public function isLive(): bool
    {
        return filled(config('services.telegram.token'));
    }

    /**
     * Returns null when Telegram accepted the message, otherwise a short reason. Never throws.
     *
     * @param  array<int, array<int, array{text:string, callback_data:string}>>|null  $buttons  rows of buttons under the message
     */
    public function send(string $chatId, string $text, ?array $buttons = null): ?string
    {
        $params = ['chat_id' => $chatId, 'text' => $text];
        if ($buttons) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $buttons], JSON_UNESCAPED_UNICODE);
        }

        return $this->call('sendMessage', $params);
    }

    /** The small pop-up a parent sees after tapping a button. */
    public function answerButton(string $callbackId, string $text): ?string
    {
        return $this->call('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    /** Takes the buttons away from a message that has been answered. */
    public function removeButtons(string $chatId, int $messageId): ?string
    {
        return $this->call('editMessageReplyMarkup', [
            'chat_id' => $chatId, 'message_id' => $messageId,
            'reply_markup' => json_encode(['inline_keyboard' => []]),
        ]);
    }

    /** Any Bot API method. Returns null on success, otherwise a short reason. Never throws. */
    public function call(string $method, array $params = []): ?string
    {
        if (! $this->isLive()) {
            return 'Telegram is not connected.';
        }

        try {
            $response = Http::timeout(8)->asForm()->post(
                'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method,
                $params,
            );
            if ($response->successful() && $response->json('ok')) {
                return null;
            }

            return mb_substr((string) ($response->json('description') ?? 'HTTP '.$response->status()), 0, 250);
        } catch (Throwable $e) {
            Log::warning("Telegram {$method} failed", ['error' => $e->getMessage()]);

            return __('No connection to Telegram. Try again later.');
        }
    }
}
