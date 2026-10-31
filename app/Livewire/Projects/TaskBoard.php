<?php

namespace App\Livewire\Projects;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** To Do / In Progress / Complete board for one project or product. Completing a task logs it on the timeline. */
class TaskBoard extends Component
{
    #[Locked]
    public int $projectId;

    public string $title = '';

    public string $due = '';

    public bool $high = false;

    public function mount(Project $project): void
    {
        $this->authorize('update', $project);
        $this->projectId = $project->id;
    }

    protected function project(): Project
    {
        $project = Project::visibleTo(auth()->user())->findOrFail($this->projectId);

        $this->authorize('update', $project);
        abort_if($project->isArchived(), 403, 'Archived projects are read-only.');

        return $project;
    }

    protected function task(int $id): Task
    {
        return $this->project()->tasks()->findOrFail($id);
    }

    public function add(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'due' => ['nullable', 'date'],
        ]);

        $this->project()->tasks()->create([
            'title' => trim($data['title']),
            'user_id' => auth()->id(),
            'due_date' => filled($data['due']) ? $data['due'] : null,
            'is_high' => $this->high,
        ]);

        $this->reset('title', 'due', 'high');
        $this->dispatch('tasks-changed');
    }

    /** Drag and drop (or the arrow buttons) end up here. */
    public function move(int $id, string $status): void
    {
        $task = $this->task($id);
        $to = TaskStatus::tryFrom($status);

        abort_if($to === null, 422);

        if ($task->status === $to) {
            return;
        }

        $task->moveTo($to);
        $this->dispatch('tasks-changed');

        if ($to === TaskStatus::Done) {
            // The completion note was added to the timeline: refresh the page around the board.
            $this->dispatch('entry-posted');
            $this->dispatch('toast', message: 'Done, logged on the timeline');
        }
    }

    public function setDue(int $id, ?string $date): void
    {
        validator(['date' => $date], ['date' => ['nullable', 'date']])->validate();

        $this->task($id)->update(['due_date' => filled($date) ? $date : null]);
    }

    public function toggleHigh(int $id): void
    {
        $task = $this->task($id);
        $task->update(['is_high' => ! $task->is_high]);
    }

    public function render()
    {
        $tasks = $this->project()->tasks()
            ->orderByDesc('is_high')->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')->get();

        return view('livewire.projects.task-board', [
            'columns' => collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $s) => [
                $s->value => $tasks->where('status', $s)->when($s === TaskStatus::Done, fn ($c) => $c->sortByDesc('completed_at'))->values(),
            ]),
        ]);
    }
}
