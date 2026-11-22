<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Setup help for Telegram: lists who sent a message to the school bot, with their chat ID.
 * A parent opens the bot and presses Start; then copy their chat ID into Parents → Edit → Telegram chat ID.
 */
class TelegramChats extends Command
{
    protected $signature = 'telegram:chats';

    protected $description = 'Show the chat IDs of people who wrote to the school Telegram bot';

    public function handle(): int
    {
        $token = config('services.telegram.token');
        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN is empty in .env (create a bot with @BotFather first).');

            return self::FAILURE;
        }

        $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getUpdates");
        if (! $response->json('ok')) {
            $this->error('Telegram said: '.($response->json('description') ?? 'HTTP '.$response->status()));

            return self::FAILURE;
        }

        $chats = collect($response->json('result'))
            ->map(fn ($u) => $u['message']['chat'] ?? null)->filter()->unique('id')
            ->map(fn ($c) => [$c['id'], trim(($c['first_name'] ?? '').' '.($c['last_name'] ?? '')), $c['username'] ?? '']);

        if ($chats->isEmpty()) {
            $this->warn('Nobody has written to the bot yet. Open the bot in Telegram, press Start, then run this again.');

            return self::SUCCESS;
        }
        $this->table(['Chat ID', 'Name', 'Username'], $chats->values()->all());

        return self::SUCCESS;
    }
}
