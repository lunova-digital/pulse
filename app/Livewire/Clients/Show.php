<?php

namespace App\Livewire\Clients;

use App\Enums\ClientEntryType;
use App\Enums\RoadmapTemplate;
use App\Models\Client;
use App\Models\PipelineStage;
use App\Support\Fmt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    public Client $client;

    // Log a conversation
    public string $type = 'call';

    public string $note = '';

    public string $nextDate = '';

    public string $nextTime = '';

    public bool $noFollowUp = false;

    // Modals: null | follow-up | details | project
    public ?string $modal = null;

    public string $followDate = '';

    public string $followTime = '';

    public string $projectName = '';

    public string $template = 'standard';

    /** @var array<string, string> */
    public array $details = [];

    public function mount(Client $client): void
    {
        Gate::authorize('manage-clients');
        $this->client = $client;
    }

    protected function guard(): void
    {
        Gate::authorize('manage-clients');
    }

    protected function toast(string $message): void
    {
        $this->dispatch('toast', message: $message);
    }

    protected function at(string $date, string $time): ?\Carbon\CarbonInterface
    {
        return filled($date) ? now()->parse($date.' '.(filled($time) ? $time : '00:00')) : null;
    }

    public function logUpdate(): void
    {
        $this->guard();

        $this->validate([
            'type' => ['required', Rule::enum(ClientEntryType::class)->only(ClientEntryType::postable())],
            'note' => ['required', 'string', 'max:5000', 'regex:/\S/'],
            'nextDate' => ['nullable', 'date'],
            'nextTime' => ['nullable', 'date_format:H:i'],
        ], ['note.required' => 'Write what was discussed.', 'note.regex' => 'Write what was discussed.']);

        if (blank($this->nextDate) && ! $this->noFollowUp) {
            $this->addError('nextDate', 'Set the next follow-up date, or tick “No follow-up needed”.');

            return;
        }

        $this->client->logUpdate(
            ClientEntryType::from($this->type),
            $this->note,
            $this->noFollowUp ? null : $this->at($this->nextDate, $this->nextTime),
            hasTime: filled($this->nextTime),
            noFollowUp: $this->noFollowUp,
            by: auth()->user(),
        );

        $this->reset('note', 'nextDate', 'nextTime', 'noFollowUp');
        $this->client->refresh();
        $this->toast('Update logged');
    }

    public function changeStage(int $stageId): void
    {
        $this->guard();
        $this->client->changeStage(PipelineStage::visible()->findOrFail($stageId), auth()->user());
        $this->client->refresh();
    }

    // Follow-up -----------------------------------------------------------

    public function openFollowUp(): void
    {
        $this->guard();
        $this->followDate = $this->client->next_followup_at?->format('Y-m-d') ?? '';
        $this->followTime = $this->client->followup_has_time ? $this->client->next_followup_at->format('H:i') : '';
        $this->modal = 'follow-up';
    }

    public function saveFollowUp(): void
    {
        $this->guard();
        $this->validate(['followDate' => ['nullable', 'date'], 'followTime' => ['nullable', 'date_format:H:i']]);

        $this->client->setFollowUp($this->at($this->followDate, $this->followTime), filled($this->followTime), auth()->user());
        $this->client->refresh();
        $this->modal = null;
        $this->toast('Follow-up updated');
    }

    // Details -------------------------------------------------------------

    public function openDetails(): void
    {
        $this->guard();
        $this->details = [
            'name' => $this->client->name, 'company' => (string) $this->client->company,
            'contact_name' => (string) $this->client->contact_name, 'email' => (string) $this->client->email,
            'phone' => (string) $this->client->phone, 'notes' => (string) $this->client->notes,
        ];
        $this->resetValidation();
        $this->modal = 'details';
    }

    public function saveDetails(): void
    {
        $this->guard();

        $data = $this->validate([
            'details.name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'details.company' => ['nullable', 'string', 'max:255'],
            'details.contact_name' => ['nullable', 'string', 'max:255'],
            'details.email' => ['nullable', 'email', 'max:255'],
            'details.phone' => ['nullable', 'string', 'max:50'],
            'details.notes' => ['nullable', 'string', 'max:5000'],
        ])['details'];

        $this->client->update(array_map(fn ($v) => filled($v) ? trim($v) : null, $data) + ['name' => trim($data['name'])]);
        $this->modal = null;
        $this->toast('Details saved');
    }

    // Conversion ------------------------------------------------------------

    public function openProject(): void
    {
        $this->guard();
        $this->projectName = $this->client->name.' project';
        $this->template = 'standard';
        $this->modal = 'project';
    }

    public function startProject()
    {
        $this->guard();

        $this->validate([
            'projectName' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'template' => ['required', Rule::enum(RoadmapTemplate::class)],
        ]);

        $project = $this->client->startProject($this->projectName, auth()->user(), RoadmapTemplate::from($this->template));

        session()->flash('toast', 'Project started for '.$this->client->name);

        return $this->redirectRoute('projects.show', $project);
    }

    public function closeModal(): void
    {
        $this->modal = null;
    }

    public function render()
    {
        $entries = $this->client->entries()->with(['author', 'toStage'])->get();

        return view('livewire.clients.show', [
            'stages' => PipelineStage::visible()->get(),
            'groups' => $entries->groupBy(fn ($e) => Fmt::dayGroup($e->created_at)),
            'projects' => $this->client->projects()->orderByDesc('id')->get(),
            'types' => ClientEntryType::postable(),
        ])->layoutData(['active' => 'clients', 'title' => $this->client->name]);
    }
}
