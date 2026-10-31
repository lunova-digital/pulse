<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;

class Index extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    /** all | due | overdue | stage-{id} */
    #[Url(as: 'filter')]
    public string $filter = 'all';

    public bool $showNew = false;

    public string $name = '';

    public string $company = '';

    public string $contact = '';

    public string $email = '';

    public string $phone = '';

    public bool $showStages = false;

    /** @var array<int, string> stage id => name being edited */
    public array $stageNames = [];

    public string $newStage = '';

    public function mount(): void
    {
        Gate::authorize('manage-clients');
    }

    public function openNew(): void
    {
        Gate::authorize('manage-clients');
        $this->resetValidation();
        $this->reset('name', 'company', 'contact', 'email', 'phone');
        $this->showNew = true;
    }

    public function closeNew(): void
    {
        $this->showNew = false;
    }

    public function create()
    {
        Gate::authorize('manage-clients');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'company' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $client = Client::start([
            'name' => trim($data['name']),
            'company' => filled($data['company']) ? trim($data['company']) : null,
            'contact_name' => filled($data['contact']) ? trim($data['contact']) : null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
        ], auth()->user());

        session()->flash('toast', 'Client added');

        return $this->redirectRoute('clients.show', $client);
    }

    // Pipeline stages -----------------------------------------------------

    public function openStages(): void
    {
        Gate::authorize('manage-clients');
        $this->stageNames = PipelineStage::orderBy('position')->pluck('name', 'id')->all();
        $this->resetValidation();
        $this->showStages = true;
    }

    public function closeStages(): void
    {
        $this->showStages = false;
    }

    public function renameStage(int $id): void
    {
        Gate::authorize('manage-clients');
        $name = trim($this->stageNames[$id] ?? '');

        if ($name === '') {
            $this->addError('stages', 'A stage needs a name.');

            return;
        }

        PipelineStage::findOrFail($id)->update(['name' => $name]);
    }

    public function addStage(): void
    {
        Gate::authorize('manage-clients');
        $name = trim($this->newStage);

        if ($name === '') {
            return;
        }

        $stage = PipelineStage::create(['name' => $name, 'position' => (PipelineStage::max('position') ?? 0) + 1]);
        $this->stageNames[$stage->id] = $stage->name;
        $this->newStage = '';
    }

    public function moveStage(int $id, int $by): void
    {
        Gate::authorize('manage-clients');
        $stages = PipelineStage::orderBy('position')->orderBy('id')->get()->values();
        $i = $stages->search(fn ($s) => $s->id === $id);
        $j = $i + $by;

        if ($i === false || ! isset($stages[$j])) {
            return;
        }

        $a = $stages[$i]->position;
        $b = $stages[$j]->position;
        // Positions may tie on legacy rows: renumber so a swap always changes order.
        if ($a === $b) {
            $stages->each(fn ($s, $k) => $s->update(['position' => $k]));
            $stages = PipelineStage::orderBy('position')->orderBy('id')->get()->values();
            [$a, $b] = [$stages[$i]->position, $stages[$j]->position];
        }

        $stages[$i]->update(['position' => $b]);
        $stages[$j]->update(['position' => $a]);
    }

    /** flag: won | lost */
    public function toggleFlag(int $id, string $flag): void
    {
        Gate::authorize('manage-clients');
        abort_unless(in_array($flag, ['won', 'lost'], true), 422);

        $stage = PipelineStage::findOrFail($id);
        $column = "is_{$flag}";
        $new = ! $stage->$column;

        if ($flag === 'won' && ! $new && ! PipelineStage::visible()->where('is_won', true)->whereKeyNot($id)->exists()) {
            $this->addError('stages', 'Keep at least one "won" stage: converting a client moves them there.');

            return;
        }

        $stage->update([$column => $new] + ($new ? [$flag === 'won' ? 'is_lost' : 'is_won' => false] : []));
    }

    public function toggleHidden(int $id): void
    {
        Gate::authorize('manage-clients');
        $stage = PipelineStage::findOrFail($id);

        try {
            $stage->is_hidden ? $stage->update(['is_hidden' => false]) : $stage->hide();
        } catch (LogicException $e) {
            $this->addError('stages', $e->getMessage());
        }
    }

    public function render()
    {
        Gate::authorize('manage-clients');

        $term = trim($this->search);
        $stages = PipelineStage::visible()->withCount('clients')->get();

        $clients = Client::with(['stage', 'latestEntry'])
            ->when($this->filter === 'due', fn ($q) => $q->due())
            ->when($this->filter === 'overdue', fn ($q) => $q->overdue())
            ->when(str_starts_with($this->filter, 'stage-'), fn ($q) => $q->where('stage_id', (int) substr($this->filter, 6)))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")))
            ->orderByRaw('next_followup_at is null')
            ->orderBy('next_followup_at')
            ->orderBy('name')
            ->get();

        return view('livewire.clients.index', [
            'clients' => $clients,
            'stages' => $stages,
            'allStages' => $this->showStages ? PipelineStage::orderBy('position')->orderBy('id')->withCount('clients')->get() : collect(),
            'dueCount' => Client::due()->count(),
            'overdueCount' => Client::overdue()->count(),
        ])->layoutData(['active' => 'clients', 'title' => 'Clients']);
    }
}
