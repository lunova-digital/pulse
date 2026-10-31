<?php

namespace App\Console\Commands;

use App\Services\ReminderDigest;
use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pulse:telegram-digest')]
#[Description('Post today\'s follow-ups and unchecked priorities to the Telegram group')]
class SendTelegramDigest extends Command
{
    public function handle(Telegram $telegram, ReminderDigest $digest): int
    {
        if (! $telegram->configured()) {
            $this->warn('Telegram is not configured (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID).');

            return self::SUCCESS;
        }

        $message = $digest->build();

        if ($message === null) {
            $this->info('Nothing to report today.');

            return self::SUCCESS;
        }

        if (! $telegram->send($message)) {
            $this->error('Telegram rejected the message.');

            return self::FAILURE;
        }

        $this->info('Digest sent.');

        return self::SUCCESS;
    }
}
