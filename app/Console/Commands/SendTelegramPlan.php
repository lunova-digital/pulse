<?php

namespace App\Console\Commands;

use App\Services\ReminderDigest;
use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pulse:telegram-plan')]
#[Description('Remind the owner (personal chat, or the group as a fallback) to set tomorrow\'s priority')]
class SendTelegramPlan extends Command
{
    public function handle(Telegram $telegram, ReminderDigest $digest): int
    {
        if (! $telegram->ownerReachable()) {
            $this->warn('Telegram is not configured (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID).');

            return self::SUCCESS;
        }

        if (! $telegram->sendToOwner($digest->planTomorrow())) {
            $this->error('Telegram rejected the message.');

            return self::FAILURE;
        }

        $this->info('Plan reminder sent.');

        return self::SUCCESS;
    }
}
