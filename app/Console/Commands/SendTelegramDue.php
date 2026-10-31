<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ReminderLog;
use App\Services\ReminderDigest;
use App\Services\Telegram;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pulse:telegram-due')]
#[Description('Ping the Telegram group when a follow-up with a set time arrives')]
class SendTelegramDue extends Command
{
    /** Older than this and the morning digest already covered it. */
    private const WINDOW_HOURS = 6;

    public function handle(Telegram $telegram, ReminderDigest $digest): int
    {
        if (! $telegram->configured()) {
            return self::SUCCESS;
        }

        $clients = Client::with('stage')
            ->where('followup_has_time', true)
            ->where('next_followup_at', '<=', now())
            ->where('next_followup_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
            ->get();

        $sent = 0;

        foreach ($clients as $client) {
            // One ping per client per scheduled time.
            if (ReminderLog::where(['client_id' => $client->id, 'kind' => 'due', 'sent_for' => $client->next_followup_at])->exists()) {
                continue;
            }

            $text = '<b>Follow-up now</b>'."\n".$digest->clientLink($client).' · '.e($client->stage->name)
                .($client->latestEntry?->note ? "\n".e(str($client->latestEntry->note)->limit(160)) : '');

            if ($telegram->send($text)) {
                ReminderLog::create(['client_id' => $client->id, 'kind' => 'due', 'sent_for' => $client->next_followup_at]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
