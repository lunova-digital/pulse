<?php

namespace App\Livewire\Projects;

use App\Enums\ProjectKind;
use App\Enums\RoadmapTemplate;
use App\Enums\WhoseMove;
use App\Livewire\Concerns\RefreshesOnEntry;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\TimelineEntry;
use App\Support\Fmt;
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

    /** @var list<int> project ids ticked for a bulk skip */
    public array $selected = [];

    public bool $selecting = false;

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

    protected function editable(int $id): Project
    {
        $project = Project::active()->ofKind(ProjectKind::from($this->kind))->visibleTo(auth()->user())->findOrFail($id);

        $this->authorize('update', $project);

        return $project;
    }

    /** Pin = today's priority. It stays pinned the next day unless changed. */
    public function togglePin(int $id): void
    {
        $project = $this->editable($id);
        $project->togglePriority();
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatch('toast', message: $project->is_pinned ? 'Priority' : 'Priority removed');
    }

    /** Nothing to report today: hides the project until tomorrow. Allowed for priority items too. */
    public function skip(int $id): void
    {
        $this->editable($id)->skipToday(auth()->user());
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatch('toast', message: 'Skipped for today');
    }

    public function toggleSelecting(): void
    {
        $this->selecting = ! $this->selecting;
        $this->selected = [];
    }

    /** Skip for today: not working on these now. Priority (pinned) projects are never skipped in bulk. */
    public function skipSelected(): void
    {
        $projects = Project::active()->ofKind(ProjectKind::from($this->kind))->visibleTo(auth()->user())
            ->where('is_pinned', false)
            ->whereIn('id', array_map('intval', $this->selected))->get();

        foreach ($projects as $project) {
            $this->authorize('update', $project);
            $project->skipToday(auth()->user());
        }

        $this->selected = [];
        $this->selecting = false;
        $this->dispatch('toast', message: $projects->count() === 1 ? 'Skipped 1 for today' : "Skipped {$projects->count()} for today");
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

        $skippedIds = DailySkip::whereDate('skipped_on', today()->toDateString())->whereNotNull('project_id')->select('project_id');

        $projects = Project::active()
            ->ofKind($kind)
            ->whereNotIn('id', $skippedIds)
            ->visibleTo(auth()->user())
            ->with('latestEntry')
            ->when(in_array($this->filter, ['us', 'client', 'none'], true), fn ($q) => $q->where('whose_move', $this->filter))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('client_name', 'like', "%{$term}%")))
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->get();

        $updatedToday = TimelineEntry::where('created_at', '>=', today())->distinct()->pluck('project_id');
        $untouched = $projects->reject(fn (Project $p) => $updatedToday->contains($p->id))->count();

        $visible = Project::active()->ofKind($kind)->visibleTo(auth()->user())->select('projects.id');

        $skips = DailySkip::with(['project:id,name', 'user:id,name'])
            ->whereIn('project_id', $visible)
            ->orderByDesc('skipped_on')->orderByDesc('id')->limit(100)->get()
            ->groupBy(fn (DailySkip $s) => Fmt::dayGroup($s->skipped_on));

        return view('livewire.projects.index', ['projects' => $projects->sortBy(fn (Project $p) => $updatedToday->contains($p->id) ? 1 : 0)->values(), 'updatedToday' => $updatedToday, 'untouched' => $untouched, 'skips' => $skips, 'kindLabel' => $kind->label(), 'kindPlural' => $kind->plural()])
            ->layoutData(['active' => $kind->plural() === 'Products' ? 'products' : 'projects', 'title' => $kind->plural()]);
    }
}
