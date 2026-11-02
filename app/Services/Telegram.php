<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Telegram
{
    public static function configured(): bool
    {
        return (bool) config('services.telegram.bot_token');
    }

    public function send(string $chatId, string $html): bool
    {
        if (! self::configured()) {
            return false;
        }

        $res = Http::timeout(15)->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
        if (! $res->ok()) {
            Log::warning('Telegram send failed', ['chat_id' => $chatId, 'status' => $res->status(), 'body' => $res->body()]);
        }

        return $res->ok();
    }
}
