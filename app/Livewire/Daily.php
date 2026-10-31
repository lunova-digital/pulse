<?php

namespace App\Livewire;

use App\Exceptions\HighPrioritySkipNotConfirmed;
use App\Livewire\Concerns\RefreshesOnEntry;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimelineEntry;
use App\Support\Fmt;
use Livewire\Component;

/** The daily check-in: every active project gets an update today, or is skipped on purpose. */
class Daily extends Component
{
    use RefreshesOnEntry;

    /** @var list<int> project ids ticked for a bulk skip */
    public array $selected = [];

    /** Pending skip of a high priority item: kind (project|task), id and step (1 or 2). */
    public ?array $confirm = null;

    protected function user()
    {
        return auth()->user();
    }

    protected function findItem(string $kind, int $id): Project|Task
    {
        return $kind === 'task'
            ? Task::visibleTo($this->user())->open()->findOrFail($id)
            : Project::active()->visibleTo($this->user())->findOrFail($id);
    }

    /** A single skip. Normal items skip silently; high priority ones ask twice. */
    public function skip(string $kind, int $id): void
    {
        $item = $this->findItem($kind, $id);
        $high = $kind === 'task' ? $item->isHighToday() : $item->isPriorityToday();

        if ($high) {
            $this->confirm = ['kind' => $kind, 'id' => $id, 'step' => 1, 'name' => $kind === 'task' ? $item->title : $item->name];

            return;
        }

        $item->skipToday($this->user());
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatch('toast', message: 'Skipped for today');
    }

    public function advanceConfirm(): void
    {
        if (! $this->confirm) {
            return;
        }

        if ($this->confirm['step'] === 1) {
            $this->confirm['step'] = 2;

            return;
        }

        $item = $this->findItem($this->confirm['kind'], $this->confirm['id']);

        try {
            $item->skipToday($this->user(), confirmedHigh: true);
        } catch (HighPrioritySkipNotConfirmed) {
            return;
        }

        $this->confirm = null;
        $this->dispatch('toast', message: 'High priority item skipped for today');
    }

    public function cancelConfirm(): void
    {
        $this->confirm = null;
    }

    /** Bulk skip. High priority items are never skipped in bulk. */
    public function skipSelected(): void
    {
        $projects = Project::active()->visibleTo($this->user())->whereIn('id', array_map('intval', $this->selected))->get();
        $skipped = 0;

        foreach ($projects as $project) {
            if ($project->isPriorityToday()) {
                continue;
            }

            $project->skipToday($this->user());
            $skipped++;
        }

        $this->selected = [];
        $this->dispatch('toast', message: $skipped === 1 ? 'Skipped 1 for today' : "Skipped {$skipped} for today");
    }

    public function selectAllOthers(): void
    {
        $this->selected = $this->otherProjects()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** when: today | tomorrow | clear */
    public function setPriority(int $id, string $when): void
    {
        $project = Project::active()->visibleTo($this->user())->findOrFail($id);

        $project->pinForDate(match ($when) {
            'today' => today(),
            'tomorrow' => today()->addDay(),
            default => null,
        });

        $this->dispatch('toast', message: match ($when) {
            'today' => 'Priority for today',
            'tomorrow' => 'Priority for tomorrow',
            default => 'Priority cleared',
        });
    }

    public function completeTask(int $id): void
    {
        $task = Task::visibleTo($this->user())->with('project')->findOrFail($id);
        $task->complete();
        $this->dispatch('toast', message: 'Task done');
    }

    /** Project ids with a timeline entry today, from anyone. */
    protected function checkedIds()
    {
        return TimelineEntry::where('created_at', '>=', today())->distinct()->pluck('project_id');
    }

    /** Active, visible projects that still need a check-in and are not today's priority. */
    protected function otherProjects()
    {
        $checked = $this->checkedIds();

        return Project::active()->visibleTo($this->user())
            ->where(fn ($q) => $q->whereNull('priority_for')->orWhereDate('priority_for', '!=', today()->toDateString()))
            ->whereNotIn('id', DailySkip::whereDate('skipped_on', today()->toDateString())->whereNotNull('project_id')->select('project_id'))
            ->whereNotIn('id', $checked)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $user = $this->user();
        $today = today()->toDateString();
        $checkedIds = $this->checkedIds();

        $skippedProjects = DailySkip::whereDate('skipped_on', $today)->whereNotNull('project_id')->pluck('project_id');
        $skippedTasks = DailySkip::whereDate('skipped_on', $today)->whereNotNull('task_id')->pluck('task_id');

        $visible = Project::active()->visibleTo($user)->with('latestEntry')->orderBy('name')->get()
            ->reject(fn (Project $p) => $skippedProjects->contains($p->id));

        [$priority, $others] = $visible->partition(fn (Project $p) => $p->isPriorityToday());

        $priorityTasks = Task::visibleTo($user)->dailyPriority()->with('project')->orderBy('due_date')->get()
            ->reject(fn (Task $t) => $skippedTasks->contains($t->id));

        $doneTasks = Task::visibleTo($user)->done()->whereDate('completed_at', $today)
            ->where(fn ($q) => $q->where('is_high', true)->orWhereDate('due_date', '<=', $today))->get();

        $checked = fn (Project $p) => $checkedIds->contains($p->id);
        $priority = $priority->sortBy(fn (Project $p) => $checked($p) ? 1 : 0)->values();
        $others = $others->sortBy(fn (Project $p) => $checked($p) ? 1 : 0)->values();

        $tomorrow = Project::active()->visibleTo($user)->priorityOn(today()->addDay())->orderBy('name')->get();

        $skips = DailySkip::with(['project:id,name', 'task:id,title', 'user:id,name'])
            ->where(fn ($q) => $user->isOwner() ? $q : $q
                ->whereIn('project_id', Project::visibleTo($user)->select('projects.id'))
                ->orWhereIn('task_id', Task::visibleTo($user)->select('tasks.id')))
            ->orderByDesc('skipped_on')->orderByDesc('id')->limit(150)->get()
            ->groupBy(fn (DailySkip $s) => Fmt::dayGroup($s->skipped_on));

        $total = $visible->count() + $skippedProjects->count();
        $done = $visible->filter($checked)->count() + $skippedProjects->count();

        return view('livewire.daily', [
            'priority' => $priority,
            'priorityTasks' => $priorityTasks,
            'doneTasks' => $doneTasks,
            'others' => $others,
            'checkedIds' => $checkedIds,
            'tomorrow' => $tomorrow,
            'skips' => $skips,
            'total' => $total,
            'done' => $done,
        ])->layoutData(['active' => 'daily', 'title' => 'Daily']);
    }
}
