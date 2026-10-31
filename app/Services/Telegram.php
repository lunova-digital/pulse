<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Posts to the team's Telegram group through the bot. Does nothing until a token and chat id are set. */
class Telegram
{
    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id'));
    }

    /** Personal chat of the owner (they must press Start on the bot once), for reminders meant for one person. */
    public function personalConfigured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.owner_chat_id'));
    }

    /**
     * @param string $html Telegram HTML (escape user text with e()).
     * @param string|null $chatId Defaults to the team group.
     */
    public function send(string $html, ?string $chatId = null): bool
    {
        if (! $this->configured()) {
            return false;
        }

        try {
            $response = Http::timeout(10)->asForm()->post(
                'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage',
                [
                    'chat_id' => $chatId ?? config('services.telegram.chat_id'),
                    'text' => $html,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ],
            );
        } catch (Throwable $e) {
            // Never log the exception message: it can contain the bot token in the URL.
            Log::warning('Telegram send failed: '.class_basename($e));

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Telegram send failed with status '.$response->status());
        }

        return $response->successful();
    }
}
