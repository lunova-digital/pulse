<?php

namespace App\Livewire;

use App\Livewire\Forms\UpdateForm;
use App\Models\Project;
use Livewire\Attributes\On;
use Livewire\Component;

/** Global "Add update" panel (side panel on desktop, bottom sheet on mobile). */
class AddUpdate extends Component
{
    public bool $open = false;

    /** True when opened for a specific project (no picker). */
    public bool $fixed = false;

    public UpdateForm $form;

    #[On('open-update')]
    public function show(?int $projectId = null, array $preset = []): void
    {
        $project = $projectId ? Project::active()->find($projectId) : null;

        $this->form->resetValidation();
        $this->form->fillFor($project, $preset);
        $this->fixed = $project !== null;
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function updatedFormProjectId($value): void
    {
        // Picking a project starts from its current whose-move.
        if ($project = Project::active()->find($value)) {
            $this->form->move = $project->whose_move->value;
        }
    }

    public function post(): void
    {
        $this->form->submit();

        $name = Project::find($this->form->projectId)->name;

        $this->open = false;
        $this->dispatch('entry-posted');
        $this->dispatch('toast', message: "Update posted to {$name}");
    }

    public function render()
    {
        return view('livewire.add-update', [
            'projects' => $this->open && ! $this->fixed ? Project::active()->orderBy('name')->get(['id', 'name']) : collect(),
            'projectName' => $this->fixed ? Project::find($this->form->projectId)?->name : null,
            'labels' => $this->open ? Project::statusLabelSuggestions() : [],
        ]);
    }
}
