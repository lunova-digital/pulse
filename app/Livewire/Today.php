<?php

namespace App\Livewire;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Livewire\Component;

/** What to work on now: overdue and due-today tasks, plus whatever is In Progress. */
class Today extends Component
{
    protected function task(int $id): Task
    {
        $task = Task::visibleTo(auth()->user())->open()->with('project')->findOrFail($id);

        if ($task->project) {
            $this->authorize('update', $task->project);
        }

        return $task;
    }

    public function done(int $id): void
    {
        $task = $this->task($id);
        $task->moveTo(TaskStatus::Done);

        $this->dispatch('toast', message: $task->project ? 'Done, logged on '.$task->project->name : 'Done');
    }

    public function start(int $id): void
    {
        $this->task($id)->moveTo(TaskStatus::InProgress);
        $this->dispatch('toast', message: 'In progress');
    }

    public function render()
    {
        $open = Task::visibleTo(auth()->user())->open()
            ->where(fn ($q) => $q->whereNull('project_id')->orWhereIn('project_id', Project::active()->select('projects.id')))
            ->with('project')
            ->orderByDesc('is_high')->orderBy('due_date')->orderBy('id')->get();

        $overdue = $open->filter(fn (Task $t) => $t->due_date?->lt(today()));
        $dueToday = $open->filter(fn (Task $t) => $t->due_date?->isSameDay(today()));
        $inProgress = $open->filter(fn (Task $t) => $t->isInProgress() && ! $overdue->contains($t) && ! $dueToday->contains($t));

        $sections = [
            ['key' => 'overdue', 'title' => 'Overdue', 'empty' => 'Nothing overdue.', 'rows' => $overdue],
            ['key' => 'today', 'title' => 'Due today', 'empty' => 'Nothing due today.', 'rows' => $dueToday],
            ['key' => 'progress', 'title' => 'In progress', 'empty' => 'Nothing in progress.', 'rows' => $inProgress],
        ];

        return view('livewire.today', ['sections' => $sections])
            ->layoutData(['active' => 'today', 'title' => 'Today']);
    }
}
