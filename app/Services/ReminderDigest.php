<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\TimelineEntry;

/** Builds the morning message: who to follow up with, and which priorities still need an update. */
class ReminderDigest
{
    /** Null when there is nothing to say. */
    public function build(): ?string
    {
        $due = Client::due()->with('stage')->orderBy('next_followup_at')->get();
        $today = $due->filter(fn (Client $c) => ! $c->isOverdue());
        $overdue = $due->filter(fn (Client $c) => $c->isOverdue());

        $checked = TimelineEntry::where('created_at', '>=', today())->select('project_id');
        $skipped = DailySkip::whereDate('skipped_on', today()->toDateString())->whereNotNull('project_id')->select('project_id');
        $priority = Project::active()->priorityOn(today())
            ->whereNotIn('id', $checked)->whereNotIn('id', $skipped)->orderBy('name')->get();

        if ($today->isEmpty() && $overdue->isEmpty() && $priority->isEmpty()) {
            return null;
        }

        $lines = ['<b>Pulse · '.e(now()->format('l, j M')).'</b>'];

        if ($today->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '<b>Follow up today ('.$today->count().')</b>';
            foreach ($today as $client) {
                $lines[] = '• '.$this->clientLink($client).' · '.e($client->stage->name)
                    .($client->followup_has_time ? ' · '.e($client->next_followup_at->format('g:i A')) : '');
            }
        }

        if ($overdue->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '<b>Overdue ('.$overdue->count().')</b>';
            foreach ($overdue as $client) {
                $days = (int) $client->next_followup_at->copy()->startOfDay()->diffInDays(today());
                $lines[] = '• '.$this->clientLink($client).' · '.$days.'d overdue';
            }
        }

        if ($priority->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '<b>Priority today, no update yet ('.$priority->count().')</b>';
            foreach ($priority as $project) {
                $lines[] = '• <a href="'.e(route('projects.show', $project)).'">'.e($project->name).'</a>';
            }
        }

        return implode("\n", $lines);
    }

    public function clientLink(Client $client): string
    {
        return '<a href="'.e(route('clients.show', $client)).'">'.e($client->name).'</a>';
    }
}
