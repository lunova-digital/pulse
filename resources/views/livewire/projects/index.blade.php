<div class="flex flex-col gap-6" style="animation: pFade 200ms">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="h1 flex-1">{{ $kindPlural }}</h1>
        <button type="button" wire:click="openNew" class="btn-primary"><x-icon name="plus" />New {{ strtolower($kindLabel) }}</button>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <label class="relative flex max-w-[400px] flex-[1_1_280px] items-center">
            <span class="pointer-events-none absolute left-4 text-text2"><x-icon name="search" /></span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search {{ strtolower($kindPlural) }} or clients" class="field pl-12">
        </label>
        <div class="flex flex-wrap gap-2">
            @foreach (['all' => 'All', 'us' => 'Waiting on us', 'client' => 'Waiting on client', 'none' => 'No action'] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['chip', 'chip-on' => $filter === $key, 'chip-off' => $filter !== $key])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($projects->isEmpty())
        <div class="card"><x-joy>No {{ strtolower($kindPlural) }} match.</x-joy></div>
    @endif

    <div class="grid grid-cols-1 gap-4 min-[600px]:grid-cols-2 lg:grid-cols-4">
        @foreach ($projects as $project)
            <a wire:key="project-{{ $project->id }}" href="{{ route('projects.show', $project) }}"
                class="card flex min-h-[200px] min-w-0 flex-col gap-2 p-4 text-text transition-colors hover:border-text2 hover:no-underline">
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1 text-base leading-6 font-semibold">{{ $project->name }}</div>
                    @if ($project->is_pinned)
                        <span title="Pinned" class="pt-[3px] text-text2"><x-icon name="pin" /></span>
                    @endif
                </div>
                <div><x-move-pill :project="$project" /></div>
                <div class="line-clamp-2 text-pretty">{{ $project->statusLine() }}</div>
                @if ($project->next_meeting_at)
                    <div class="flex items-center gap-2 text-text2"><x-icon name="cal" /><span>Meet: {{ \App\Support\Fmt::meeting($project->next_meeting_at) }}</span></div>
                @endif
                <div class="mt-auto flex flex-wrap items-center gap-2 pt-2 text-text2">
                    <span class="flex-1 whitespace-nowrap">Updated {{ \App\Support\Fmt::ago($project->last_activity_at) }}</span>
                    @if ($project->meetingNotLogged())<span class="tag-red">Meeting not logged</span>@endif
                    @if ($project->isStale())<span class="tag-gray">Stale</span>@endif
                </div>
            </a>
        @endforeach
    </div>

    @if ($showNew)
        <x-modal title="New {{ strtolower($kindLabel) }}" close="closeNew">
            <div class="flex flex-1 flex-col gap-4 overflow-auto p-6">
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">{{ $kindLabel }} name</span>
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
                <button type="button" wire:click="create" x-data x-bind:disabled="!$wire.name.trim()" class="btn-primary">Create {{ strtolower($kindLabel) }}</button>
            </div>
        </x-modal>
    @endif
</div>
