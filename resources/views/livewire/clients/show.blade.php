<div class="mx-auto flex max-w-[800px] flex-col gap-6" style="animation: pFade 200ms">
    <a href="{{ route('clients.index') }}" class="-my-2 -ml-2 flex h-11 items-center gap-2 self-start rounded-lg px-2 font-semibold text-text2 hover:bg-hover hover:text-text hover:no-underline">
        <x-icon name="back" />Clients
    </a>

    {{-- Header --}}
    <section class="card flex flex-col gap-4 p-4 md:p-6">
        <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <h1 class="h1 text-pretty">{{ $client->name }}</h1>
                <div class="text-text2">{{ collect([$client->company, $client->contact_name])->filter()->join(' · ') }}</div>
                <div class="text-text2">{{ collect([$client->email, $client->phone])->filter()->join(' · ') }}</div>
            </div>
            <button type="button" wire:click="openDetails" class="btn-ghost">Edit</button>
        </div>
        @if ($client->notes)<div class="text-pretty text-text2">{{ $client->notes }}</div>@endif

        <div class="flex flex-col gap-2">
            <div class="font-semibold">Stage</div>
            <div class="flex flex-wrap gap-2">
                @foreach ($stages as $stage)
                    <button type="button" wire:key="st-{{ $stage->id }}" wire:click="changeStage({{ $stage->id }})" @class(['chip', 'chip-on' => $client->stage_id === $stage->id, 'chip-off' => $client->stage_id !== $stage->id])>{{ $stage->name }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <button type="button" wire:click="openFollowUp" class="inline-flex h-11 items-center gap-2 rounded-lg border border-border px-4 font-semibold">
                <x-icon name="cal" />
                <span @class(['text-red' => $client->isOverdue(), 'text-accent-fg' => $client->isDueToday()])>
                    {{ $client->next_followup_at ? 'Follow up: '.$client->followUpLabel() : 'Set next follow-up' }}
                </span>
            </button>
            <button type="button" wire:click="openProject" class="btn-primary"><x-icon name="plus" />Start project</button>
        </div>
    </section>

    {{-- Projects they became --}}
    @if ($projects->isNotEmpty())
        <section class="card overflow-hidden">
            <div class="p-4"><h2 class="h2">Projects</h2></div>
            @foreach ($projects as $project)
                <a wire:key="cp-{{ $project->id }}" href="{{ route('projects.show', $project) }}" class="flex min-h-14 items-center gap-4 border-t border-border px-4 text-text hover:bg-hover hover:no-underline">
                    <span class="min-w-0 flex-1 truncate font-semibold">{{ $project->name }}</span>
                    <span class="tag-gray">{{ $project->kind->label() }}</span>
                    <x-move-pill :project="$project" />
                </a>
            @endforeach
        </section>
    @endif

    {{-- Log a conversation --}}
    <section class="card flex flex-col gap-4 p-4 md:p-6">
        <h2 class="h2">Log a conversation</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($types as $t)
                <button type="button" wire:click="$set('type', '{{ $t->value }}')" @class(['chip pl-2', 'chip-on' => $type === $t->value, 'chip-off' => $type !== $t->value])><x-icon :name="$t->icon()" />{{ $t->label() }}</button>
            @endforeach
        </div>
        <label class="flex flex-col gap-2">
            <span class="font-semibold">What happened, and where does the conversation stand?</span>
            <textarea wire:model="note" rows="3" class="textarea min-h-24"></textarea>
            @error('note')<span class="text-red">{{ $message }}</span>@enderror
        </label>
        <div class="flex flex-col gap-2">
            <div class="font-semibold">Next follow-up</div>
            <div class="flex flex-wrap items-center gap-2">
                <input type="date" wire:model="nextDate" @disabled($noFollowUp) class="field w-auto px-2" aria-label="Follow-up date">
                <input type="time" wire:model="nextTime" @disabled($noFollowUp) class="field w-auto px-2" aria-label="Follow-up time (optional)">
                <label class="flex items-center gap-2 text-text2"><input type="checkbox" wire:model.live="noFollowUp" class="size-4 accent-accent">No follow-up needed</label>
            </div>
            @error('nextDate')<span class="text-red">{{ $message }}</span>@enderror
            @error('nextTime')<span class="text-red">Use a valid time.</span>@enderror
        </div>
        <div class="flex justify-end">
            <button type="button" wire:click="logUpdate" x-data x-bind:disabled="!$wire.note.trim()" class="btn-primary">Log update</button>
        </div>
    </section>

    {{-- History --}}
    <section class="flex flex-col gap-4">
        <h2 class="h2">History</h2>
        @if ($groups->isEmpty())<div class="card"><x-joy>Nothing logged yet.</x-joy></div>@endif
        <div class="flex flex-col">
            @foreach ($groups as $day => $entries)
                <div class="pt-2 pb-4 font-semibold text-text2">{{ $day }}</div>
                @foreach ($entries as $entry)
                    <div wire:key="ce-{{ $entry->id }}" class="flex gap-4">
                        <div class="flex shrink-0 flex-col items-center">
                            <div class="flex size-10 items-center justify-center rounded-full border border-border bg-card text-text2"><x-icon :name="$entry->type->icon()" /></div>
                            <div class="min-h-4 w-px flex-1 bg-border"></div>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-2 pt-2 pb-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $entry->type->label() }}</span>
                                @if ($entry->type === \App\Enums\ClientEntryType::StageChange && $entry->toStage)<span class="tag-gray">{{ $entry->toStage->name }}</span>@endif
                                @if ($entry->next_followup_at && $entry->type !== \App\Enums\ClientEntryType::FollowUp)<span class="tag-gray">Next: {{ $entry->next_followup_at->format('D, j M') }}</span>@endif
                                <span class="ml-auto whitespace-nowrap text-text2">{{ $entry->author ? $entry->author->name.' · ' : '' }}{{ $entry->created_at->format('g:i A') }}</span>
                            </div>
                            @if ($entry->note)<div class="leading-6 text-pretty">{{ $entry->note }}</div>@endif
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </section>

    @if ($modal === 'follow-up')
        <x-modal title="Next follow-up" close="closeModal">
            <div class="flex flex-col gap-4 p-6">
                <div class="flex flex-wrap gap-2">
                    <input type="date" wire:model="followDate" class="field w-auto px-2" aria-label="Date">
                    <input type="time" wire:model="followTime" class="field w-auto px-2" aria-label="Time (optional)">
                </div>
                <div class="text-text2">Leave the time empty to be reminded in the morning digest only. Clear the date to remove the follow-up.</div>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="saveFollowUp" class="btn-primary">Save</button>
            </div>
        </x-modal>
    @endif

    @if ($modal === 'details')
        <x-modal title="Client details" close="closeModal">
            <div class="flex flex-1 flex-col gap-4 overflow-auto p-6">
                @foreach (['name' => 'Name', 'company' => 'Company', 'contact_name' => 'Contact person', 'email' => 'Email', 'phone' => 'Phone'] as $key => $label)
                    <label class="flex flex-col gap-2"><span class="font-semibold">{{ $label }}</span>
                        <input wire:model="details.{{ $key }}" class="field">
                        @error('details.'.$key)<span class="text-red">{{ $message }}</span>@enderror</label>
                @endforeach
                <label class="flex flex-col gap-2"><span class="font-semibold">Notes</span><textarea wire:model="details.notes" rows="3" class="textarea min-h-24"></textarea></label>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="saveDetails" class="btn-primary">Save</button>
            </div>
        </x-modal>
    @endif

    @if ($modal === 'project')
        <x-modal title="Start a project" close="closeModal">
            <div class="flex flex-col gap-4 p-6">
                <div class="text-text2">Creates a project under “{{ $client->name }}” and moves them to the won stage.</div>
                <label class="flex flex-col gap-2"><span class="font-semibold">Project name</span>
                    <input wire:model="projectName" class="field" autofocus>
                    @error('projectName')<span class="text-red">{{ $message }}</span>@enderror</label>
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Roadmap template</div>
                    @foreach (\App\Enums\RoadmapTemplate::cases() as $tpl)
                        <button type="button" wire:click="$set('template', '{{ $tpl->value }}')" @class(['flex min-h-14 flex-col items-start justify-center rounded-lg border px-4 py-2 text-left transition-all',
                            'border-accent bg-accent-t' => $template === $tpl->value, 'border-border bg-transparent' => $template !== $tpl->value])>
                            <span class="font-semibold">{{ $tpl->label() }}</span>
                            <span class="text-text2">{{ implode(' → ', $tpl->steps()) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="startProject" x-data x-bind:disabled="!$wire.projectName.trim()" class="btn-primary">Start project</button>
            </div>
        </x-modal>
    @endif
</div>
