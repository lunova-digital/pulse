<?php

namespace App\Livewire\Projects;

use App\Enums\EntryType;
use App\Enums\MilestoneState;
use App\Enums\WhoseMove;
use App\Livewire\Concerns\RefreshesOnEntry;
use App\Livewire\Forms\UpdateForm;
use App\Models\Project;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use RefreshesOnEntry;

    public Project $project;

    public UpdateForm $form;

    /** Timeline filter: all, an EntryType value, or "archive" (archived + reopened). */
    public string $filter = 'all';

    public string $meeting = '';

    /** null | archive | reopen | roadmap */
    public ?string $modal = null;

    public string $reason = '';

    /** @var array{steps: list<array{k: string, name: string}>, cur: int} */
    public array $rm = ['steps' => [], 'cur' => 0];

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
        $this->meeting = $project->next_meeting_at?->format('Y-m-d\TH:i') ?? '';
        $this->form->fillFor($project->isArchived() || ! auth()->user()->can('update', $project) ? null : $project);
    }

    protected function ensureEditable(): void
    {
        $this->authorize('update', $this->project);
        abort_if($this->project->isArchived(), 403, 'Archived projects are read-only.');
    }

    protected function toast(string $message): void
    {
        $this->dispatch('toast', message: $message);
    }

    public function skipToday(): void
    {
        $this->ensureEditable();
        $this->project->skipToday(auth()->user());
        $this->toast('Skipped for today');
    }

    #[On('tasks-changed')]
    public function refreshTasks(): void
    {
        // Intentionally empty: handling the event re-renders the open-task count on the tab.
    }

    public function togglePin(): void
    {
        $this->ensureEditable();
        $this->project->togglePriority();
        $this->toast($this->project->is_pinned ? 'Priority' : 'Priority removed');
    }

    public function changeMove(string $move): void
    {
        $this->ensureEditable();
        abort_unless($this->project->tracksWhoseMove(), 403, 'Products have no client to wait on.');

        if ($this->project->changeWhoseMove(WhoseMove::from($move))) {
            $this->form->move = $move;
        }
    }

    public function updatedMeeting($value): void
    {
        $this->ensureEditable();
        $this->validateOnly('meeting', ['meeting' => ['nullable', 'date']]);

        $this->project->scheduleMeeting(filled($value) ? now()->parse($value) : null);
        $this->toast('Next meeting updated');
    }

    public function postUpdate(): void
    {
        $this->ensureEditable();
        $this->form->submit();
        $this->project->refresh();
        $this->toast('Update posted to '.$this->project->name);
    }

    // Archive / reopen ----------------------------------------------------

    public function openArchive(): void
    {
        $this->ensureEditable();
        $this->reset('reason');
        $this->resetValidation();
        $this->modal = 'archive';
    }

    public function openReopen(): void
    {
        $this->authorize('update', $this->project);
        abort_unless($this->project->isArchived(), 403);
        $this->reset('reason');
        $this->resetValidation();
        $this->modal = 'reopen';
    }

    public function closeModal(): void
    {
        $this->modal = null;
    }

    public function confirmReason()
    {
        $this->validate(['reason' => ['required', 'string', 'max:2000', 'regex:/\S/']], [
            'reason.required' => 'A reason is required.',
            'reason.regex' => 'A reason is required.',
        ]);

        if ($this->modal === 'archive') {
            $this->ensureEditable();
            $this->project->archive($this->reason);
            session()->flash('toast', $this->project->name.' archived');

            return $this->redirectRoute('archived');
        }

        abort_unless($this->modal === 'reopen', 422);
        $this->authorize('update', $this->project);
        $this->project->reopen($this->reason);
        $this->form->fillFor($this->project);
        $this->modal = null;
        $this->reset('reason');
        $this->toast($this->project->name.' reopened');
    }

    // Roadmap -------------------------------------------------------------

    public function openRoadmap(): void
    {
        $this->ensureEditable();

        $milestones = $this->project->milestones;
        $this->rm = [
            'steps' => $milestones->map(fn ($m) => ['k' => (string) Str::uuid(), 'name' => $m->title])->all(),
            'cur' => max(0, $milestones->search(fn ($m) => $m->state === MilestoneState::Current) ?: 0),
        ];
        $this->modal = 'roadmap';
    }

    public function setCurrent(int $i): void
    {
        $this->rm['cur'] = $i;
    }

    public function moveStep(int $i, int $by): void
    {
        $j = $i + $by;
        $steps = $this->rm['steps'];

        if (! isset($steps[$i], $steps[$j])) {
            return;
        }

        [$steps[$i], $steps[$j]] = [$steps[$j], $steps[$i]];
        $this->rm['steps'] = $steps;

        if ($this->rm['cur'] === $i) {
            $this->rm['cur'] = $j;
        } elseif ($this->rm['cur'] === $j) {
            $this->rm['cur'] = $i;
        }
    }

    public function removeStep(int $i): void
    {
        if (count($this->rm['steps']) <= 1) {
            return;
        }

        array_splice($this->rm['steps'], $i, 1);

        if ($i < $this->rm['cur']) {
            $this->rm['cur']--;
        }

        $this->rm['cur'] = min($this->rm['cur'], count($this->rm['steps']) - 1);
    }

    public function addStep(): void
    {
        $this->rm['steps'][] = ['k' => (string) Str::uuid(), 'name' => 'New milestone'];
    }

    public function saveRoadmap(): void
    {
        $this->ensureEditable();

        $titles = collect($this->rm['steps'])->pluck('name')->map(fn ($n) => trim($n))->all();
        // Keep "current" pointing at the same step after blank steps are dropped.
        $cur = count(array_filter(array_slice($titles, 0, $this->rm['cur']), fn ($t) => $t !== ''));

        if (count(array_filter($titles, fn ($t) => $t !== '')) === 0) {
            $this->addError('rm', 'Add at least one milestone.');

            return;
        }

        $this->project->saveRoadmap($titles, $cur);
        $this->modal = null;
        $this->toast('Roadmap saved');
    }

    public function render()
    {
        $user = auth()->user();
        $canEdit = $user->can('update', $this->project);

        $entries = $this->project->entries()->with('author')
            ->when(! $canEdit, fn ($q) => $q->where('visible_to_client', true))
            ->when($this->filter === 'archive', fn ($q) => $q->whereIn('type', [EntryType::Archived, EntryType::Reopened]))
            ->when($this->filter !== 'all' && $this->filter !== 'archive' && EntryType::tryFrom($this->filter), fn ($q) => $q->where('type', $this->filter))
            ->get();

        $archived = $this->project->isArchived();

        return view('livewire.projects.show', [
            'milestones' => $this->project->milestones,
            'groups' => $entries->groupBy(fn ($e) => \App\Support\Fmt::dayGroup($e->created_at)),
            'archived' => $archived,
            'labels' => $archived || ! $canEdit ? [] : Project::statusLabelSuggestions($user),
            'canEdit' => $canEdit,
            'backUrl' => $canEdit ? ($archived ? route('archived') : route('work.index')) : null,
            'backLabel' => $archived ? 'Archived' : 'Work',
            'archiveReason' => $archived ? $this->project->archiveReason() : null,
            'openTasks' => $canEdit && ! $archived ? $this->project->tasks()->open()->count() : 0,
            'skips' => $canEdit ? $this->project->skips()->with('user:id,name')->orderByDesc('skipped_on')->limit(60)->get() : collect(),
            'meetingMissed' => $this->project->meetingNotLogged(),
        ])->layoutData([
            'active' => $archived ? 'archived' : 'work',
            'fabProject' => $archived || ! $canEdit ? null : $this->project->id,
            'title' => $this->project->name,
        ]);
    }
}
