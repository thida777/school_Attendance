<?php

namespace App\Console\Commands;

use App\Services\Telegram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Connects the school bot to this website, so parents' button taps and /start reach it.
 * Needs a public HTTPS address (PUBLIC_URL or APP_URL) and TELEGRAM_WEBHOOK_SECRET in .env.
 *   php artisan telegram:webhook           connect
 *   php artisan telegram:webhook --info    show what Telegram has
 *   php artisan telegram:webhook --remove  disconnect (then telegram:chats works again)
 */
class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {--info} {--remove}';

    protected $description = 'Connect the school Telegram bot to this website (parents\' answer buttons)';

    public function handle(Telegram $telegram): int
    {
        $token = config('services.telegram.token');
        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN is empty in .env.');

            return self::FAILURE;
        }

        if ($this->option('info')) {
            $info = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getWebhookInfo")->json('result') ?? [];
            $this->table(['Setting', 'Value'], collect($info)->map(fn ($v, $k) => [$k, is_scalar($v) ? (string) $v : json_encode($v)])->values()->all());

            return self::SUCCESS;
        }

        if ($this->option('remove')) {
            $error = $telegram->call('deleteWebhook');
            $error ? $this->error($error) : $this->info('Webhook removed.');

            return $error ? self::FAILURE : self::SUCCESS;
        }

        $secret = (string) config('services.telegram.webhook_secret');
        if (strlen($secret) < 20 || ! preg_match('/^[A-Za-z0-9_-]+$/', $secret)) {
            $this->error('Set TELEGRAM_WEBHOOK_SECRET in .env first: 20+ letters, digits, _ or -, e.g. '.bin2hex(random_bytes(20)));

            return self::FAILURE;
        }

        $url = rtrim((string) (env('PUBLIC_URL') ?: config('app.url')), '/').'/api/telegram/webhook';
        if (! str_starts_with($url, 'https://')) {
            $this->error("Telegram needs an HTTPS address; this one is {$url}");

            return self::FAILURE;
        }

        $error = $telegram->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => json_encode(['message', 'callback_query']),
            'drop_pending_updates' => 'true',
        ]);
        if ($error) {
            $this->error("Telegram said: {$error}");

            return self::FAILURE;
        }
        $this->info("Connected: {$url}");

        return self::SUCCESS;
    }
}
