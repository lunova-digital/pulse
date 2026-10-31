<div class="flex flex-col gap-6" style="animation: pFade 200ms">
    <div class="flex flex-wrap items-center gap-2">
        <h1 class="h1 flex-1">Clients</h1>
        <button type="button" wire:click="openStages" class="btn-secondary">Stages</button>
        <button type="button" wire:click="openNew" class="btn-primary"><x-icon name="plus" />New client</button>
    </div>

    {{-- Funnel --}}
    <div class="grid grid-cols-2 gap-2 min-[600px]:grid-cols-4 lg:flex lg:flex-wrap">
        @foreach ($stages as $stage)
            <button type="button" wire:key="funnel-{{ $stage->id }}" wire:click="$set('filter', 'stage-{{ $stage->id }}')"
                @class(['card flex min-w-0 flex-col items-start gap-1 p-4 text-left transition-colors lg:flex-1',
                    'border-accent bg-accent-t' => $filter === 'stage-'.$stage->id])>
                <span class="text-[22px] leading-8 font-bold">{{ $stage->clients_count }}</span>
                <span class="max-w-full truncate text-text2">{{ $stage->name }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <label class="relative flex max-w-[400px] flex-[1_1_280px] items-center">
            <span class="pointer-events-none absolute left-4 text-text2"><x-icon name="search" /></span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search clients" class="field pl-12">
        </label>
        <div class="flex flex-wrap gap-2">
            @foreach (['all' => 'All', 'due' => 'Due today · '.$dueCount, 'overdue' => 'Overdue · '.$overdueCount] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['chip', 'chip-on' => $filter === $key, 'chip-off' => $filter !== $key])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden">
        @if ($clients->isEmpty())
            <x-joy>No clients match.</x-joy>
        @endif
        @foreach ($clients as $client)
            <a wire:key="client-{{ $client->id }}" href="{{ route('clients.show', $client) }}"
                class="flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 border-t border-border py-2 pr-4 pl-4 text-text first:border-t-0 hover:bg-hover hover:no-underline">
                <div class="min-w-0 flex-[1_1_200px]">
                    <div class="truncate font-semibold">{{ $client->name }}</div>
                    <div class="truncate text-text2">{{ $client->latestEntry?->note ?? $client->contact_name }}</div>
                </div>
                <span class="tag-gray">{{ $client->stage->name }}</span>
                <span @class(['whitespace-nowrap md:w-52 md:text-right', 'font-semibold text-red' => $client->isOverdue(), 'font-semibold text-accent-fg' => $client->isDueToday(), 'text-text2' => ! $client->isOverdue() && ! $client->isDueToday()])>
                    {{ $client->next_followup_at ? ($client->isOverdue() ? 'Overdue · ' : '').$client->followUpLabel() : 'No follow-up set' }}
                </span>
            </a>
        @endforeach
    </div>

    @if ($showNew)
        <x-modal title="New client" close="closeNew">
            <div class="flex flex-1 flex-col gap-4 overflow-auto p-6">
                <label class="flex flex-col gap-2"><span class="font-semibold">Client name</span>
                    <input wire:model="name" class="field" autofocus>
                    @error('name')<span class="text-red">{{ $message }}</span>@enderror</label>
                <label class="flex flex-col gap-2"><span class="font-semibold">Company</span><input wire:model="company" class="field"></label>
                <label class="flex flex-col gap-2"><span class="font-semibold">Contact person</span><input wire:model="contact" class="field"></label>
                <label class="flex flex-col gap-2"><span class="font-semibold">Email</span>
                    <input type="email" wire:model="email" class="field">@error('email')<span class="text-red">{{ $message }}</span>@enderror</label>
                <label class="flex flex-col gap-2"><span class="font-semibold">Phone</span><input wire:model="phone" class="field"></label>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="closeNew" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="create" x-data x-bind:disabled="!$wire.name.trim()" class="btn-primary">Add client</button>
            </div>
        </x-modal>
    @endif

    @if ($showStages)
        <x-modal title="Pipeline stages" close="closeStages">
            <div class="flex flex-1 flex-col gap-2 overflow-auto px-6 py-4">
                <div class="pb-2 text-text2">Rename, reorder, and mark which stage means “won” (a client becomes won when you start their project) or “lost”. Stages are hidden, never deleted.</div>
                @foreach ($allStages as $stage)
                    <div wire:key="stage-{{ $stage->id }}" class="flex flex-wrap items-center gap-2 border-t border-border pt-2">
                        <input wire:model="stageNames.{{ $stage->id }}" wire:change="renameStage({{ $stage->id }})" aria-label="Stage name" @class(['field min-w-0 flex-1', 'opacity-50' => $stage->is_hidden])>
                        <button type="button" wire:click="moveStage({{ $stage->id }}, -1)" title="Move up" class="icon-btn"><x-icon name="chev-up" /></button>
                        <button type="button" wire:click="moveStage({{ $stage->id }}, 1)" title="Move down" class="icon-btn"><x-icon name="chev-down" /></button>
                        <div class="flex w-full flex-wrap gap-2">
                            <button type="button" wire:click="toggleFlag({{ $stage->id }}, 'won')" @class(['chip', 'chip-on' => $stage->is_won, 'chip-off' => ! $stage->is_won])>Won</button>
                            <button type="button" wire:click="toggleFlag({{ $stage->id }}, 'lost')" @class(['chip', 'chip-on' => $stage->is_lost, 'chip-off' => ! $stage->is_lost])>Lost</button>
                            <button type="button" wire:click="toggleHidden({{ $stage->id }})" class="chip chip-off">{{ $stage->is_hidden ? 'Show' : 'Hide' }} · {{ $stage->clients_count }} clients</button>
                        </div>
                    </div>
                @endforeach
                @error('stages')<div class="text-red">{{ $message }}</div>@enderror
                <div class="flex gap-2 border-t border-border pt-4">
                    <input wire:model="newStage" wire:keydown.enter="addStage" placeholder="New stage name" class="field min-w-0 flex-1">
                    <button type="button" wire:click="addStage" class="btn-secondary">Add</button>
                </div>
            </div>
            <div class="flex justify-end border-t border-border px-6 py-4">
                <button type="button" wire:click="closeStages" class="btn-primary">Done</button>
            </div>
        </x-modal>
    @endif
</div>
