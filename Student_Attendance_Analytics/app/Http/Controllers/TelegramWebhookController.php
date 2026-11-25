<?php

namespace App\Http\Controllers;

use App\Services\ParentReplies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/telegram/webhook: Telegram sends here what people do with the school bot
 * (button taps under parent messages, /start). Set up with: php artisan telegram:webhook
 *
 * Telegram sends the secret from TELEGRAM_WEBHOOK_SECRET in the header X-Telegram-Bot-Api-Secret-Token;
 * anything without the right secret is refused, so nobody else can fake a parent's answer.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, ParentReplies $replies): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $replies->handle($request->all());

        return response()->json(['ok' => true]); // Telegram only needs a 200 answer
    }
}
