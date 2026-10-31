<div class="mx-auto flex max-w-[800px] flex-col gap-6" style="animation: pFade 200ms">
    @if ($backUrl)
        <a href="{{ $backUrl }}"
            class="-my-2 -ml-2 flex h-11 items-center gap-2 self-start rounded-lg px-2 font-semibold text-text2 hover:bg-hover hover:text-text hover:no-underline">
            <x-icon name="back" />{{ $backLabel }}
        </a>
    @endif

    {{-- Header --}}
    <section class="card flex flex-col gap-4 p-4 md:p-6">
        <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <h1 class="h1 text-pretty">{{ $project->name }}</h1>
                <div class="text-text2">{{ $project->client_name }}</div>
            </div>
            @if ($canEdit && ! $archived)
                <button type="button" wire:click="togglePin" title="{{ $project->is_pinned ? 'Remove from priority' : 'Make priority' }}"
                    @class(['inline-flex size-11 items-center justify-center rounded-lg border transition-all',
                        'border-accent bg-accent-t text-accent-fg' => $project->is_pinned, 'border-border bg-transparent text-text2' => ! $project->is_pinned])>
                    <x-icon name="pin" />
                </button>
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                    <button type="button" x-on:click="open = !open" title="More" class="icon-btn border border-border"><x-icon name="more" /></button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" class="menu top-[52px] right-0 w-56">
                        <button type="button" wire:click="openArchive" x-on:click="open = false" class="menu-item"><x-icon name="archive" />Archive project</button>
                    </div>
                </div>
            @endif
        </div>

        @if ($project->description)
            <div class="-mt-2 text-pretty text-text2">{{ $project->description }}</div>
        @endif

        @if ($canEdit && ! $archived)
            <div class="flex flex-wrap items-center gap-4">
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                    <button type="button" x-on:click="open = !open" title="Change whose move"
                        class="inline-flex h-11 items-center gap-2 rounded-lg border border-transparent pr-2 pl-4 font-semibold {{ $project->whose_move->pill() }}">
                        <span class="size-1.5 rounded-full {{ $project->whose_move->dot() }}"></span>{{ $project->moveBadge() }}<x-icon name="chev-down" />
                    </button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false" class="menu top-[52px] left-0 w-60">
                        @foreach (\App\Enums\WhoseMove::cases() as $m)
                            <button type="button" wire:click="changeMove('{{ $m->value }}')" x-on:click="open = false" class="menu-item">
                                <span class="size-2 rounded-full {{ $m->dot() }}"></span><span class="flex-1">{{ $m->label() }}</span>
                                <span @class(['text-accent-fg', 'opacity-0' => $project->whose_move !== $m])><x-icon name="check" /></span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <label class="flex flex-wrap items-center gap-2 text-text2">
                    <x-icon name="cal" /><span>Next meeting</span>
                    <input type="datetime-local" wire:model.blur="meeting" class="field w-auto px-2">
                </label>
                @if ($meetingMissed)<span class="tag-red">Meeting not logged</span>@endif
            </div>
        @elseif (! $archived)
            <div><x-move-pill :project="$project" /></div>
        @else
            <div class="flex flex-wrap items-center gap-4">
                <x-move-pill :project="$project" />
                <span class="text-text2">Archived {{ \App\Support\Fmt::date($project->archived_at) }}</span>
            </div>
            <div class="flex flex-col gap-2 rounded-lg bg-hover p-4">
                <div class="font-semibold">Archive reason</div>
                <div class="text-pretty">{{ $archiveReason }}</div>
            </div>
            @if ($canEdit)
                <button type="button" wire:click="openReopen" class="btn-primary self-start"><x-icon name="rotate" />Reopen project</button>
            @endif
        @endif
    </section>

    {{-- Roadmap --}}
    <section class="card flex flex-col gap-6 p-4 md:p-6">
        <div class="flex items-center gap-2">
            <h2 class="h2 flex-1">Roadmap</h2>
            @if ($canEdit && ! $archived)
                <button type="button" wire:click="openRoadmap" class="btn-ghost -my-2 -mr-2">Edit roadmap</button>
            @endif
        </div>
        <div class="flex flex-col gap-2 md:flex-row">
            @foreach ($milestones as $i => $m)
                @php
                    $done = $m->state === \App\Enums\MilestoneState::Done;
                    $cur = $m->state === \App\Enums\MilestoneState::Current;
                    $last = $loop->last;
                @endphp
                <div wire:key="ms-{{ $m->id }}" @class(['flex min-w-0 flex-row gap-2 md:flex-col', 'flex-none' => $last, 'flex-none md:flex-1' => ! $last])>
                    <div class="flex flex-col items-center gap-2 md:flex-row">
                        <div @class(['flex size-8 shrink-0 items-center justify-center rounded-full border-2 font-semibold transition-all',
                            'border-green bg-green text-white' => $done, 'border-accent bg-accent text-white' => $cur, 'border-border bg-transparent text-text2' => ! $done && ! $cur])>
                            @if ($done)<x-icon name="check" />@else{{ $i + 1 }}@endif
                        </div>
                        @unless ($last)
                            <div @class(['min-h-6 w-0.5 flex-1 rounded-[1px] md:h-0.5 md:min-h-0 md:w-auto md:min-w-4', 'bg-green' => $done, 'bg-border' => ! $done])></div>
                        @endunless
                    </div>
                    <div class="flex min-w-0 flex-col pt-1.5 pr-2 md:pt-0">
                        <span @class(['font-semibold text-accent-fg' => $cur, 'text-text' => $done, 'text-text2' => ! $done && ! $cur])>{{ $m->title }}</span>
                        @if ($cur)<span class="text-text2">Current</span>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Add update --}}
    @if ($canEdit && ! $archived)
        <section class="card flex flex-col gap-4 p-4 md:p-6">
            <h2 class="h2">Add update</h2>
            <x-update-fields :labels="$labels" :rows="3" />
            <div class="flex justify-end">
                <button type="button" wire:click="postUpdate" x-data
                    x-bind:disabled="!($wire.form.label.trim() || $wire.form.note.trim())" class="btn-primary">Post update</button>
            </div>
        </section>
    @endif

    {{-- Timeline --}}
    <section class="flex flex-col gap-4">
        <h2 class="h2">Timeline</h2>
        <div class="flex flex-wrap gap-2">
            @php
                $filters = ['all' => 'All'] + collect(\App\Enums\EntryType::postable())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all() + ['archive' => 'Archive/Reopen'];
            @endphp
            @foreach ($filters as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')" @class(['chip', 'chip-on' => $filter === $key, 'chip-off' => $filter !== $key])>{{ $label }}</button>
            @endforeach
        </div>

        @if ($groups->isEmpty())
            <div class="card"><x-joy>No entries of this type.</x-joy></div>
        @endif

        <div class="flex flex-col">
            @foreach ($groups as $day => $entries)
                <div class="pt-2 pb-4 font-semibold text-text2">{{ $day }}</div>
                @foreach ($entries as $entry)
                    <div wire:key="entry-{{ $entry->id }}" class="flex gap-4">
                        <div class="flex shrink-0 flex-col items-center">
                            <div class="flex size-10 items-center justify-center rounded-full border border-border bg-card text-text2"><x-icon :name="$entry->type->icon()" /></div>
                            <div class="min-h-4 w-px flex-1 bg-border"></div>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-2 pt-2 pb-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $entry->type->label() }}</span>
                                @if ($entry->status_label)
                                    <x-move-pill :move="$entry->whose_move" :label="$entry->status_label" />
                                @endif
                                @if ($canEdit && ! $entry->visible_to_client)<span class="tag-gray">Internal</span>@endif
                                <span class="ml-auto whitespace-nowrap text-text2">{{ $canEdit && $entry->author ? $entry->author->name.' · ' : '' }}{{ $entry->created_at->format('g:i A') }}</span>
                            </div>
                            @if ($entry->note)<div class="leading-6 text-pretty">{{ $entry->note }}</div>@endif
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </section>

    {{-- Archive / reopen --}}
    @if (in_array($modal, ['archive', 'reopen'], true))
        @php $isArchive = $modal === 'archive'; @endphp
        <x-modal close="closeModal">
            <div class="flex flex-col gap-4 overflow-auto p-6">
                <div class="flex flex-col gap-2">
                    <div class="h2 text-pretty">{{ $isArchive ? 'Archive' : 'Reopen' }} {{ $project->name }}?</div>
                    <div class="text-pretty text-text2">{{ $isArchive ? 'It moves to the Archived tab with its full timeline. You can reopen it any time.' : 'It moves back to Projects and the reason is added to its timeline.' }}</div>
                </div>
                <label class="flex flex-col gap-2">
                    <span class="font-semibold">{{ $isArchive ? 'Why are you archiving this?' : 'Why are you reopening this?' }}</span>
                    <textarea wire:model="reason" rows="4" placeholder="Required" class="textarea min-h-28" autofocus></textarea>
                    @error('reason')<span class="text-red">{{ $message }}</span>@enderror
                </label>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="confirmReason" x-data x-bind:disabled="!$wire.reason.trim()" class="btn-primary">{{ $isArchive ? 'Archive' : 'Reopen' }}</button>
            </div>
        </x-modal>
    @endif

    {{-- Roadmap editor --}}
    @if ($modal === 'roadmap')
        <x-modal title="Edit roadmap" close="closeModal">
            <div class="flex flex-1 flex-col gap-2 overflow-auto px-6 py-4">
                <div class="pb-2 text-text2">Rename, reorder or add milestones. Select the circle to mark the current one.</div>
                @php $count = count($rm['steps']); @endphp
                @foreach ($rm['steps'] as $i => $step)
                    <div wire:key="rm-{{ $step['k'] }}" class="flex items-center">
                        <button type="button" wire:click="setCurrent({{ $i }})" title="Mark as current" class="flex size-11 shrink-0 items-center justify-center border-0 bg-transparent p-0">
                            <span @class(['flex size-5 items-center justify-center rounded-full border-2', 'border-accent' => $rm['cur'] === $i, 'border-border' => $rm['cur'] !== $i])>
                                <span @class(['size-2.5 rounded-full', 'bg-accent' => $rm['cur'] === $i, 'bg-transparent' => $rm['cur'] !== $i])></span>
                            </span>
                        </button>
                        <input wire:model="rm.steps.{{ $i }}.name" aria-label="Milestone name" class="field mr-2 min-w-0 flex-1">
                        <button type="button" wire:click="moveStep({{ $i }}, -1)" title="Move up" @class(['icon-btn', 'opacity-30' => $i === 0])><x-icon name="chev-up" /></button>
                        <button type="button" wire:click="moveStep({{ $i }}, 1)" title="Move down" @class(['icon-btn', 'opacity-30' => $i === $count - 1])><x-icon name="chev-down" /></button>
                        <button type="button" wire:click="removeStep({{ $i }})" title="Remove milestone" class="icon-btn"><x-icon name="x" /></button>
                    </div>
                @endforeach
                @error('rm')<div class="text-red">{{ $message }}</div>@enderror
                <button type="button" wire:click="addStep" class="btn-ghost self-start"><x-icon name="plus" />Add milestone</button>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="closeModal" class="btn-secondary">Cancel</button>
                <button type="button" wire:click="saveRoadmap" class="btn-primary">Save roadmap</button>
            </div>
        </x-modal>
    @endif
</div>
