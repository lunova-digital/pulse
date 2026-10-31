<div class="flex flex-col gap-6" style="animation: pFade 200ms">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="h1 flex-1">Work</h1>
        @if ($projects->isNotEmpty())
            <button type="button" wire:click="toggleSelecting" @class(['btn-secondary', 'border-accent' => $selecting])>{{ $selecting ? 'Done' : 'Select' }}</button>
        @endif
        <button type="button" wire:click="openNew" class="btn-primary"><x-icon name="plus" />New</button>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <label class="relative flex max-w-[400px] flex-[1_1_280px] items-center">
            <span class="pointer-events-none absolute left-4 text-text2"><x-icon name="search" /></span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search projects, products or clients" class="field pl-12">
        </label>
        <div class="flex flex-wrap gap-2">
            @foreach (['all' => 'All types', 'project' => 'Projects', 'product' => 'Products'] as $key => $label)
                <button type="button" wire:click="$set('type', '{{ $key }}')" @class(['chip', 'chip-on' => $type === $key, 'chip-off' => $type !== $key])>{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (['all' => 'All', 'us' => 'Waiting on us', 'client' => 'Waiting on client', 'none' => 'No action'] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['chip', 'chip-on' => $filter === $key, 'chip-off' => $filter !== $key])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($projects->isEmpty())
        <div class="card"><x-joy>Nothing matches.</x-joy></div>
    @endif

    <div @class(['rounded-lg border px-4 py-2', 'border-border bg-hover text-text2' => $untouched === 0, 'border-accent bg-accent-t text-accent-fg' => $untouched > 0])>
        @if ($untouched > 0)
            <b>{{ $untouched }}</b> not touched today. Post an update or skip each one; the bot keeps reminding until they are cleared.
        @else
            Everything is touched today.
        @endif
    </div>

    @if ($selecting)
        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-hover px-4 py-2">
            <span class="flex-1 text-text2">{{ count($selected) }} selected. Not working on them today? Skip them and they come back tomorrow.</span>
            <button type="button" wire:click="skipSelected" @disabled(! $selected) class="btn-primary">Skip for today</button>
        </div>
    @endif

    @php($pinned = $projects->where('is_pinned', true))
    @foreach ([['Priority today', $pinned], [$pinned->isEmpty() ? null : 'Everything else', $projects->where('is_pinned', false)]] as [$heading, $group])
        @continue($group->isEmpty())
        @if ($heading)<h2 class="h2 -mb-2">{{ $heading }} <span class="text-text2">{{ $group->count() }}</span></h2>@endif
        <div class="grid grid-cols-1 gap-4 min-[600px]:grid-cols-2 lg:grid-cols-4">
            @foreach ($group as $project)
                <div wire:key="project-{{ $project->id }}"
                    @class(['card relative flex min-h-[200px] min-w-0 flex-col gap-2 p-4 text-text transition-colors hover:border-text2', 'border-accent' => $project->is_pinned])>
                    <div class="flex items-start gap-2">
                        @if ($selecting && ! $project->is_pinned)
                            <input type="checkbox" wire:model.live="selected" value="{{ $project->id }}" aria-label="Select {{ $project->name }}" class="mt-1 size-5 shrink-0 accent-accent">
                        @endif
                        <a href="{{ route('projects.show', $project) }}" class="min-w-0 flex-1 text-base leading-6 font-semibold text-text after:absolute after:inset-0 after:content-[''] hover:no-underline">{{ $project->name }}</a>
                        @can('update', $project)
                            <button type="button" wire:click="togglePin({{ $project->id }})" title="{{ $project->is_pinned ? 'Remove from priority' : 'Make priority' }}"
                                @class(['relative z-10 inline-flex size-9 shrink-0 items-center justify-center rounded-lg border transition-all',
                                    'border-accent bg-accent-t text-accent-fg' => $project->is_pinned, 'border-border bg-transparent text-text2 hover:text-text' => ! $project->is_pinned])>
                                <x-icon name="pin" />
                            </button>
                        @endcan
                    </div>
                    <div class="flex flex-wrap items-center gap-2"><x-move-pill :project="$project" /><span class="tag-gray">{{ $project->kind->label() }}</span></div>
                    <div class="line-clamp-2 text-pretty">{{ $project->statusLine() }}</div>
                    @if ($project->next_meeting_at)
                        <div class="flex items-center gap-2 text-text2"><x-icon name="cal" /><span>Meet: {{ \App\Support\Fmt::meeting($project->next_meeting_at) }}</span></div>
                    @endif
                    <div class="mt-auto flex flex-wrap items-center gap-2 pt-2 text-text2">
                        @if ($updatedToday->contains($project->id))
                            <span class="tag-gray">Updated today</span>
                        @else
                            @can('update', $project)
                                <button type="button" wire:click="skip({{ $project->id }})" title="Nothing to report today"
                                    class="btn-secondary relative z-10 min-h-9 px-3 py-1">Skip today</button>
                            @endcan
                        @endif
                        <span class="flex-1 whitespace-nowrap">Updated {{ \App\Support\Fmt::ago($project->last_activity_at) }}</span>
                        @if ($project->meetingNotLogged())<span class="tag-red">Meeting not logged</span>@endif
                        @if ($project->isStale())<span class="tag-gray">Stale</span>@endif
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    {{-- Skip history: collapsed by default --}}
    <details class="card overflow-hidden" wire:key="skip-history">
        <summary class="flex min-h-14 cursor-pointer items-center gap-2 px-4 font-semibold">
            Skip history
            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-lg bg-hover px-2 font-semibold text-text2">{{ $skips->flatten()->count() }}</span>
        </summary>
        @if ($skips->isEmpty())
            <x-joy>No skips yet.</x-joy>
        @endif
        @foreach ($skips as $day => $items)
            <div class="border-t border-border bg-hover px-4 py-2 font-semibold text-text2">{{ $day }}</div>
            @foreach ($items as $skip)
                <div wire:key="skip-{{ $skip->id }}" class="flex flex-wrap items-center gap-2 border-t border-border px-4 py-2">
                    <span class="min-w-0 flex-1 truncate">{{ $skip->project?->name }}</span>
                    @if ($skip->was_high)<span class="tag-gray">Was priority</span>@endif
                    <span class="text-text2">{{ $skip->user?->name }}</span>
                </div>
            @endforeach
        @endforeach
    </details>

    @if ($showNew)
        <x-modal title="New project or product" close="closeNew">
            <div class="flex flex-1 flex-col gap-4 overflow-auto p-6">
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Type</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Enums\ProjectKind::cases() as $k)
                            <button type="button" wire:click="$set('kind', '{{ $k->value }}')" @class(['chip', 'chip-on' => $kind === $k->value, 'chip-off' => $kind !== $k->value])>{{ $k->label() }}</button>
                        @endforeach
                    </div>
                </div>
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Name</span>
                    <input wire:model="name" placeholder="e.g. Clinic booking site" class="field" autofocus>
                    @error('name')<span class="text-red">{{ $message }}</span>@enderror
                </label>
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Client name</span>
                    <input wire:model="client" placeholder="e.g. Royal Housing Ltd." class="field">
                </label>
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Description</span>
                    <textarea wire:model="description" rows="2" placeholder="One line about the project" class="textarea min-h-[72px]"></textarea>
                </label>
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Roadmap template</div>
                    @foreach (\App\Enums\RoadmapTemplate::cases() as $tpl)
                        <button type="button" wire:click="$set('template', '{{ $tpl->value }}')"
                            @class(['flex min-h-14 flex-col items-start justify-center rounded-lg border px-4 py-2 text-left transition-all',
                                'border-accent bg-accent-t' => $template === $tpl->value, 'border-border bg-transparent' => $template !== $tpl->value])>
                            <span @class(['font-semibold', 'text-accent-fg' => $template === $tpl->value, 'text-text' => $template !== $tpl->value])>{{ $tpl->label() }}</span>
                            <span class="text-text2">{{ implode(' → ', $tpl->steps()) }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="flex flex-col gap-2">
                    <div class="font-semibold">Whose move?</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Enums\WhoseMove::cases() as $m)
                            <button type="button" wire:click="$set('move', '{{ $m->value }}')" @class(['chip', $m->chipOn() => $move === $m->value, 'chip-off' => $move !== $m->value])>
                                <span class="size-1.5 rounded-full {{ $m->dot() }}"></span>{{ $m->label() }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">Next meeting (optional)</span>
                    <input type="datetime-local" wire:model="meeting" class="field">
                </label>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="closeNew" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="create" x-data x-bind:disabled="!$wire.name.trim()" class="btn-primary">Create</button>
            </div>
        </x-modal>
    @endif
</div>
