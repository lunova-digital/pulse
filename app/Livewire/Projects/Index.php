<?php

namespace App\Livewire\Projects;

use App\Enums\ProjectKind;
use App\Enums\RoadmapTemplate;
use App\Enums\WhoseMove;
use App\Livewire\Concerns\RefreshesOnEntry;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use RefreshesOnEntry;

    /** project | product: the route decides which list this page shows. */
    #[Locked]
    public string $kind = 'project';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'filter')]
    public string $filter = 'all';

    public bool $showNew = false;

    public string $name = '';

    public string $client = '';

    public string $description = '';

    public string $template = 'standard';

    public string $move = 'us';

    public string $meeting = '';

    public function mount(string $kind = 'project'): void
    {
        $this->kind = ProjectKind::from($kind)->value;
    }

    public function openNew(): void
    {
        $this->resetValidation();
        $this->reset('name', 'client', 'description', 'template', 'move', 'meeting');
        $this->showNew = true;
    }

    public function closeNew(): void
    {
        $this->showNew = false;
    }

    public function create()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'template' => ['required', Rule::enum(RoadmapTemplate::class)],
            'move' => ['required', Rule::enum(WhoseMove::class)],
            'meeting' => ['nullable', 'date'],
        ]);

        $project = Project::start([
            'name' => trim($data['name']),
            'client_name' => filled($data['client']) ? trim($data['client']) : null,
            'description' => filled($data['description']) ? trim($data['description']) : null,
            'whose_move' => WhoseMove::from($data['move']),
            'next_meeting_at' => filled($data['meeting']) ? $data['meeting'] : null,
            'kind' => ProjectKind::from($this->kind),
        ], RoadmapTemplate::from($data['template']), auth()->user());

        session()->flash('toast', ProjectKind::from($this->kind)->label().' created');

        return $this->redirectRoute('projects.show', $project);
    }

    public function render()
    {
        $term = trim($this->search);

        $kind = ProjectKind::from($this->kind);

        $projects = Project::active()
            ->ofKind($kind)
            ->visibleTo(auth()->user())
            ->with('latestEntry')
            ->when(in_array($this->filter, ['us', 'client', 'none'], true), fn ($q) => $q->where('whose_move', $this->filter))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('client_name', 'like', "%{$term}%")))
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->get();

        return view('livewire.projects.index', ['projects' => $projects, 'kindLabel' => $kind->label(), 'kindPlural' => $kind->plural()])
            ->layoutData(['active' => $kind->plural() === 'Products' ? 'products' : 'projects', 'title' => $kind->plural()]);
    }
}
