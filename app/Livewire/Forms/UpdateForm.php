<?php

namespace App\Livewire\Forms;

use App\Enums\EntryType;
use App\Enums\WhoseMove;
use App\Models\Project;
use App\Models\TimelineEntry;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class UpdateForm extends Form
{
    public ?int $projectId = null;

    public string $type = 'note';

    public string $label = '';

    public string $note = '';

    public string $move = 'us';

    /** Hidden from client-portal users. */
    public bool $internal = false;

    protected function rules(): array
    {
        return [
            'projectId' => ['required', Rule::exists('projects', 'id')->whereNull('archived_at')],
            'type' => ['required', Rule::enum(EntryType::class)->only(EntryType::postable())],
            'label' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'move' => ['required', Rule::enum(WhoseMove::class)],
        ];
    }

    protected function messages(): array
    {
        return ['projectId.required' => 'Choose a project.'];
    }

    public function fillFor(?Project $project, array $preset = []): void
    {
        $this->reset();
        $this->projectId = $project?->id;
        $this->move = ($project?->whose_move ?? WhoseMove::Us)->value;
        $this->fill(array_intersect_key($preset, array_flip(['type', 'label', 'note', 'move'])));
    }

    public function canPost(): bool
    {
        return $this->projectId && (trim($this->label) !== '' || trim($this->note) !== '');
    }

    public function submit(): TimelineEntry
    {
        $this->validate();

        if (! $this->canPost()) {
            throw ValidationException::withMessages(['form.label' => 'Add a status label or a note.']);
        }

        $type = EntryType::from($this->type);
        $project = Project::active()->visibleTo(auth()->user())->findOrFail($this->projectId);

        $entry = $project->postEntry(
            $type,
            trim($this->label) !== '' ? $this->label : $type->label(),
            $this->note,
            WhoseMove::from($this->move),
            $this->internal,
        );

        $this->reset('label', 'note', 'internal');
        $this->move = $project->whose_move->value;

        return $entry;
    }
}
