<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use Livewire\Component;

class Tasks extends Component
{
    public string $tab = 'open';

    public string $title = '';

    public ?int $projectId = null;

    public string $due = '';

    public function add(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'projectId' => ['nullable', 'exists:projects,id'],
            'due' => ['nullable', 'date'],
        ]);

        Task::create([
            'title' => trim($data['title']),
            'project_id' => $data['projectId'] ?: null,
            'due_date' => filled($data['due']) ? $data['due'] : null,
        ]);

        $this->reset('title', 'due');
        $this->tab = 'open';
    }

    public function toggle(int $id): void
    {
        $task = Task::with('project')->findOrFail($id);

        if ($task->isDone()) {
            $task->reopen();

            return;
        }

        $task->complete();

        if ($task->project && ! $task->project->isArchived()) {
            $this->dispatch('toast', message: 'Logged on '.$task->project->name.' timeline');
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'done' ? 'done' : 'open';
    }

    public function render()
    {
        $tasks = Task::with('project')
            ->when($this->tab === 'open',
                fn ($q) => $q->open()->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id'),
                fn ($q) => $q->done()->orderByDesc('completed_at'))
            ->get();

        return view('livewire.tasks', [
            'tasks' => $tasks,
            'openCount' => Task::open()->count(),
            'doneCount' => Task::done()->count(),
            // Archived projects are read-only, so tasks can only link to active ones.
            'projects' => Project::active()->orderBy('name')->get(['id', 'name']),
        ])->layoutData(['active' => 'tasks', 'title' => 'Tasks']);
    }
}
