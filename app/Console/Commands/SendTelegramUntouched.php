<?php

namespace App\Console\Commands;

use App\Services\ReminderDigest;
use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pulse:telegram-untouched')]
#[Description('Remind the owner (personal chat, or the group as a fallback) about projects with no update or skip today')]
class SendTelegramUntouched extends Command
{
    public function handle(Telegram $telegram, ReminderDigest $digest): int
    {
        if (! $telegram->ownerReachable()) {
            $this->warn('Telegram is not configured (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID).');

            return self::SUCCESS;
        }

        $message = $digest->untouched();

        if ($message === null) {
            $this->info('Everything is touched today.');

            return self::SUCCESS;
        }

        if (! $telegram->sendToOwner($message)) {
            $this->error('Telegram rejected the message.');

            return self::FAILURE;
        }

        $this->info('Reminder sent.');

        return self::SUCCESS;
    }
}
