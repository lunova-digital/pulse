<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\Task;

/** Builds the morning message (follow-ups and today's priority) and the evening prompt to set tomorrow's. */
class ReminderDigest
{
    /** Null when there is nothing to say. */
    public function build(): ?string
    {
        $due = Client::due()->with('stage')->orderBy('next_followup_at')->get();
        $today = $due->filter(fn (Client $c) => ! $c->isOverdue());
        $overdue = $due->filter(fn (Client $c) => $c->isOverdue());

        $priority = $this->priorities();

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
            $lines[] = '<b>Priority today ('.$priority->count().')</b>';
            foreach ($priority as $project) {
                $lines[] = '• <a href="'.e(route('projects.show', $project)).'">'.e($project->name).'</a>';
            }
        }

        return implode("\n", $lines);
    }

    /** The "not touched yet" nudge for projects and tasks. Null once everything has an update, a skip or is done. */
    public function untouched(int $show = 25): ?string
    {
        $projects = Project::untouchedToday();
        $tasks = Task::untouchedToday();
        $projectCount = $projects->count();
        $taskCount = $tasks->count();

        if ($projectCount + $taskCount === 0) {
            return null;
        }

        $parts = array_filter([
            $projectCount ? $projectCount.($projectCount === 1 ? ' project' : ' projects') : null,
            $taskCount ? $taskCount.($taskCount === 1 ? ' task' : ' tasks') : null,
        ]);
        $lines = ['<b>Pulse · '.implode(' and ', $parts).' not touched today</b>', 'Post an update, finish or skip each one for today.'];

        if ($projectCount) {
            $lines[] = '';
            $lines[] = '<b>Projects and products</b>';
            $list = $projects->orderByDesc('is_pinned')->orderBy('name')->limit($show)->get();
            foreach ($list as $project) {
                $lines[] = '• <a href="'.e(route('projects.show', $project)).'">'.e($project->name).'</a>'.($project->is_pinned ? ' ★' : '');
            }
            if ($projectCount > $list->count()) {
                $lines[] = '…and '.($projectCount - $list->count()).' more';
            }
        }

        if ($taskCount) {
            $lines[] = '';
            $lines[] = '<b>Tasks</b>';
            $list = $tasks->orderByDesc('is_high')->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')->limit($show)->get();
            foreach ($list as $task) {
                $lines[] = '• '.e($task->title).($task->is_high ? ' ★' : '');
            }
            if ($taskCount > $list->count()) {
                $lines[] = '…and '.($taskCount - $list->count()).' more';
            }
        }

        $lines[] = '';
        $lines[] = '<a href="'.e(route('work.index')).'">Open Pulse</a>';

        return implode("\n", $lines);
    }

    /** Pinned projects that were not skipped today. */
    protected function priorities()
    {
        $skipped = DailySkip::whereDate('skipped_on', today()->toDateString())->whereNotNull('project_id')->select('project_id');

        return Project::active()->where('is_pinned', true)->whereNotIn('id', $skipped)->orderBy('name')->get();
    }

    /** The evening prompt. Priorities carry over, so it also says what tomorrow looks like if nothing changes. */
    public function planTomorrow(): string
    {
        $priority = $this->priorities();
        $lines = ['<b>Pulse · plan tomorrow</b>', '', 'Set tomorrow\'s priority: pin the projects and products you will work on.'];

        if ($priority->isEmpty()) {
            $lines[] = 'Nothing is pinned right now.';
        } else {
            $lines[] = 'If you change nothing, these stay as priority:';
            foreach ($priority as $project) {
                $lines[] = '• <a href="'.e(route('projects.show', $project)).'">'.e($project->name).'</a>';
            }
        }

        $lines[] = '';
        $lines[] = '<a href="'.e(route('work.index')).'">Open Pulse</a>';

        return implode("\n", $lines);
    }

    public function clientLink(Client $client): string
    {
        return '<a href="'.e(route('clients.show', $client)).'">'.e($client->name).'</a>';
    }
}
