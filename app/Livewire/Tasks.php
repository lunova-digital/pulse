<?php

namespace App\Livewire;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Livewire\Component;

/** Every task in one flat list. The board on each project page is where tasks are normally worked. */
class Tasks extends Component
{
    public string $tab = 'open';

    public string $title = '';

    public ?int $projectId = null;

    public string $due = '';

    public bool $high = false;

    public function add(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'projectId' => ['nullable', 'exists:projects,id'],
            'due' => ['nullable', 'date'],
        ]);

        $user = auth()->user();

        if ($data['projectId']) {
            // Only active projects the user can edit.
            $this->authorize('update', Project::active()->visibleTo($user)->findOrFail($data['projectId']));
        }

        Task::create([
            'title' => trim($data['title']),
            'project_id' => $data['projectId'] ?: null,
            'user_id' => $user->id,
            'due_date' => filled($data['due']) ? $data['due'] : null,
            'is_high' => $this->high,
        ]);

        $this->reset('title', 'due', 'high');
        $this->tab = 'open';
    }

    public function toggle(int $id): void
    {
        $task = Task::visibleTo(auth()->user())->with('project')->findOrFail($id);

        if ($task->project) {
            $this->authorize('update', $task->project);
        }

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

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'done' ? 'done' : 'open';
    }

    public function render()
    {
        $user = auth()->user();

        $tasks = Task::visibleTo($user)->with('project')
            ->when($this->tab === 'open',
                fn ($q) => $q->open()->orderByDesc('is_high')->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id'),
                fn ($q) => $q->done()->orderByDesc('completed_at'))
            ->get();

        return view('livewire.tasks', [
            'tasks' => $tasks,
            'openCount' => Task::visibleTo($user)->open()->count(),
            'doneCount' => Task::visibleTo($user)->done()->count(),
            // Archived projects are read-only, so tasks can only link to active ones.
            'projects' => Project::active()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ])->layoutData(['active' => 'tasks', 'title' => 'Tasks']);
    }
}
