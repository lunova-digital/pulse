<?php

namespace App\Livewire;

use App\Enums\Recurrence;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\Task;
use App\Support\Fmt;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Tasks extends Component
{
    public string $tab = 'open';

    public string $title = '';

    public ?int $projectId = null;

    public string $due = '';

    public bool $high = false;

    public string $repeat = '';

    /** @var list<int> */
    public array $selected = [];

    public bool $selecting = false;

    public function add(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'projectId' => ['nullable', 'exists:projects,id'],
            'due' => ['nullable', 'date'],
            'repeat' => ['nullable', Rule::enum(Recurrence::class)],
        ]);

        $user = auth()->user();

        if ($data['projectId']) {
            // Only active projects the user can see.
            Project::active()->visibleTo($user)->findOrFail($data['projectId']);
        }

        Task::create([
            'title' => trim($data['title']),
            'project_id' => $data['projectId'] ?: null,
            'user_id' => $user->id,
            'due_date' => filled($data['due']) ? $data['due'] : null,
            'is_high' => $this->high,
            'recurrence' => filled($data['repeat']) ? $data['repeat'] : null,
        ]);

        $this->reset('title', 'due', 'high', 'repeat');
        $this->tab = 'open';
    }

    public function toggle(int $id): void
    {
        $task = Task::visibleTo(auth()->user())->with('project')->findOrFail($id);

        if ($task->isDone()) {
            $task->reopen();

            return;
        }

        $task->complete();

        if ($task->project && ! $task->project->isArchived()) {
            $this->dispatch('toast', message: 'Logged on '.$task->project->name.' timeline');
        }
    }

    public function toggleHigh(int $id): void
    {
        $task = Task::visibleTo(auth()->user())->findOrFail($id);
        $task->update(['is_high' => ! $task->is_high]);
    }

    /** Nothing to do today: hide it until tomorrow (a recurring task moves to its next day). */
    public function skip(int $id): void
    {
        Task::visibleTo(auth()->user())->open()->findOrFail($id)->skipToday(auth()->user());
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatch('toast', message: 'Skipped for today');
    }

    public function toggleSelecting(): void
    {
        $this->selecting = ! $this->selecting;
        $this->selected = [];
    }

    /** Bulk skip. High priority tasks are skipped one by one only. */
    public function skipSelected(): void
    {
        $tasks = Task::visibleTo(auth()->user())->open()->where('is_high', false)
            ->whereIn('tasks.id', array_map('intval', $this->selected))->get();

        $tasks->each(fn (Task $t) => $t->skipToday(auth()->user()));

        $this->selected = [];
        $this->selecting = false;
        $this->dispatch('toast', message: $tasks->count() === 1 ? 'Skipped 1 for today' : "Skipped {$tasks->count()} for today");
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'done' ? 'done' : 'open';
    }

    public function render()
    {
        $user = auth()->user();

        $skippedToday = DailySkip::whereDate('skipped_on', today()->toDateString())->whereNotNull('task_id')->select('task_id');

        $tasks = Task::visibleTo($user)->with('project')
            ->when($this->tab === 'open',
                fn ($q) => $q->open()->whereNotIn('tasks.id', $skippedToday)->orderByDesc('is_high')->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id'),
                fn ($q) => $q->done()->orderByDesc('completed_at'))
            ->get();

        $skips = DailySkip::with(['task:id,title,recurrence', 'user:id,name'])
            ->whereIn('task_id', Task::visibleTo($user)->select('tasks.id'))
            ->orderByDesc('skipped_on')->orderByDesc('id')->limit(100)->get()
            ->groupBy(fn (DailySkip $s) => Fmt::dayGroup($s->skipped_on));

        return view('livewire.tasks', [
            'tasks' => $tasks,
            'skips' => $skips,
            'untouched' => Task::visibleTo($user)->untouchedToday()->count(),
            'upcomingFrom' => today()->toDateString(),
            'openCount' => Task::visibleTo($user)->open()->whereNotIn('tasks.id', $skippedToday)->count(),
            'doneCount' => Task::visibleTo($user)->done()->count(),
            // Archived projects are read-only, so tasks can only link to active ones.
            'projects' => Project::active()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ])->layoutData(['active' => 'tasks', 'title' => 'Tasks']);
    }
}
