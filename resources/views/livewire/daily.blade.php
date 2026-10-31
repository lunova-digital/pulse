<div class="flex flex-col gap-6" style="animation: pFade 200ms">
    <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1">
            <h1 class="h1">Daily</h1>
            <div class="text-text2">{{ now()->format('l, j M Y') }} · {{ $done }} of {{ $total }} checked in</div>
        </div>
    </div>

    {{-- Today's priority --}}
    <section class="card overflow-hidden">
        <div class="flex items-center gap-2 p-4">
            <h2 class="h2">Today's priority</h2>
            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-lg bg-hover px-2 font-semibold text-text2">{{ $priority->count() + $priorityTasks->count() }}</span>
            <span class="ml-auto text-text2">Each needs an update, or a confirmed skip.</span>
        </div>

        @if ($priority->isEmpty() && $priorityTasks->isEmpty() && $doneTasks->isEmpty())
            <x-joy>No priority set. Pin something below or plan tomorrow.</x-joy>
        @endif

        @foreach ($priority as $project)
            @php $isChecked = $checkedIds->contains($project->id); @endphp
            <div wire:key="prio-{{ $project->id }}" class="flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 border-t border-border py-2 pr-2 pl-4">
                <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
                    <a href="{{ route('projects.show', $project) }}" class="text-base leading-6 font-semibold text-text hover:text-accent-fg hover:no-underline">{{ $project->name }}</a>
                    <span class="tag-gray">{{ $project->kind->label() }}</span>
                    <x-move-pill :project="$project" />
                </div>
                <div class="min-w-0 flex-[1_1_220px] truncate text-text2">{{ $project->statusLine() }}</div>
                <div class="ml-auto flex items-center gap-2">
                    @if ($isChecked)
                        <span class="tag-green">Update posted</span>
                    @else
                        <span class="tag-red">Needs update</span>
                        <button type="button" wire:click="skip('project', {{ $project->id }})" class="btn-secondary">Skip</button>
                        <button type="button" class="btn-primary" x-data x-on:click="Livewire.dispatch('open-update', { projectId: {{ $project->id }} })"><x-icon name="plus" />Update</button>
                    @endif
                    <button type="button" wire:click="setPriority({{ $project->id }}, 'clear')" title="Remove from priority" class="icon-btn"><x-icon name="x" /></button>
                </div>
            </div>
        @endforeach

        @foreach ($priorityTasks as $task)
            <div wire:key="ptask-{{ $task->id }}" class="flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 border-t border-border py-2 pr-2 pl-4">
                <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="text-base leading-6 font-semibold">{{ $task->title }}</span>
                    <span class="tag-gray">Task</span>
                    @if ($task->project)<a href="{{ route('projects.show', $task->project) }}" class="text-text2 hover:text-text hover:no-underline">{{ $task->project->name }}</a>@endif
                </div>
                <div class="ml-auto flex items-center gap-2">
                    @if ($task->due_date)<span @class(['whitespace-nowrap', 'text-red' => $task->isOverdue(), 'text-text2' => ! $task->isOverdue()])>{{ \App\Support\Fmt::due($task->due_date, false) }}</span>@endif
                    <button type="button" wire:click="skip('task', {{ $task->id }})" class="btn-secondary">Skip</button>
                    <button type="button" wire:click="completeTask({{ $task->id }})" class="btn-primary"><x-icon name="check" />Done</button>
                </div>
            </div>
        @endforeach

        @foreach ($doneTasks as $task)
            <div wire:key="dtask-{{ $task->id }}" class="flex min-h-14 items-center gap-4 border-t border-border py-2 pr-4 pl-4 text-text2">
                <span class="min-w-0 flex-1 truncate line-through">{{ $task->title }}</span>
                <span class="tag-green">Done today</span>
            </div>
        @endforeach
    </section>

    {{-- Everything else --}}
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-2 p-4">
            <h2 class="h2">Everything else</h2>
            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-lg bg-hover px-2 font-semibold text-text2">{{ $others->count() }}</span>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                @if (count($selected))
                    <button type="button" wire:click="clearSelection" class="btn-ghost">Clear</button>
                    <button type="button" wire:click="skipSelected" class="btn-primary">Skip selected ({{ count($selected) }})</button>
                @elseif ($others->contains(fn ($p) => ! $checkedIds->contains($p->id)))
                    <button type="button" wire:click="selectAllOthers" class="btn-ghost">Select all</button>
                @endif
            </div>
        </div>

        @if ($others->isEmpty())
            <x-joy>Everything is checked in.</x-joy>
        @endif

        @foreach ($others as $project)
            @php $isChecked = $checkedIds->contains($project->id); @endphp
            <div wire:key="other-{{ $project->id }}" class="flex min-h-16 flex-wrap items-center gap-x-2 gap-y-2 border-t border-border py-2 pr-2 pl-2">
                @unless ($isChecked)
                    <label class="flex size-11 shrink-0 cursor-pointer items-center justify-center" title="Select to skip">
                        <input type="checkbox" wire:model.live="selected" value="{{ $project->id }}" class="size-5 accent-accent">
                    </label>
                @else
                    <span class="size-11 shrink-0"></span>
                @endunless
                <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
                    <a href="{{ route('projects.show', $project) }}" class="text-base leading-6 font-semibold text-text hover:text-accent-fg hover:no-underline">{{ $project->name }}</a>
                    <span class="tag-gray">{{ $project->kind->label() }}</span>
                    <x-move-pill :project="$project" />
                </div>
                <div class="min-w-0 flex-[1_1_200px] truncate text-text2">{{ $project->statusLine() }}</div>
                <div class="ml-auto flex items-center gap-1">
                    @if ($isChecked)
                        <span class="tag-green mr-2">Update posted</span>
                    @else
                        <button type="button" wire:click="skip('project', {{ $project->id }})" class="btn-ghost">Skip</button>
                        <button type="button" title="Priority today" wire:click="setPriority({{ $project->id }}, 'today')" class="icon-btn border border-border"><x-icon name="pin" /></button>
                        <button type="button" title="Priority tomorrow" wire:click="setPriority({{ $project->id }}, 'tomorrow')" class="icon-btn border border-border"><x-icon name="cal" /></button>
                        <button type="button" title="Add update" class="icon-btn border border-border" x-data x-on:click="Livewire.dispatch('open-update', { projectId: {{ $project->id }} })"><x-icon name="plus" /></button>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    {{-- Plan tomorrow --}}
    <section class="card overflow-hidden">
        <div class="flex items-center gap-2 p-4">
            <h2 class="h2">Tomorrow's priority</h2>
            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-lg bg-hover px-2 font-semibold text-text2">{{ $tomorrow->count() }}</span>
            <span class="ml-auto text-text2">Use the calendar button on a row to add.</span>
        </div>
        @if ($tomorrow->isEmpty())
            <x-joy>Nothing planned for tomorrow yet.</x-joy>
        @endif
        @foreach ($tomorrow as $project)
            <div wire:key="tmr-{{ $project->id }}" class="flex min-h-14 items-center gap-4 border-t border-border py-2 pr-2 pl-4">
                <a href="{{ route('projects.show', $project) }}" class="min-w-0 flex-1 truncate font-semibold text-text hover:text-accent-fg hover:no-underline">{{ $project->name }}</a>
                <button type="button" wire:click="setPriority({{ $project->id }}, 'clear')" title="Remove" class="icon-btn"><x-icon name="x" /></button>
            </div>
        @endforeach
    </section>

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
                    <span class="min-w-0 flex-1 truncate">{{ $skip->project?->name ?? $skip->task?->title }}</span>
                    @if ($skip->task_id)<span class="tag-gray">Task</span>@endif
                    @if ($skip->was_high)<span class="tag-red">High priority</span>@endif
                    <span class="text-text2">{{ $skip->user?->name }}</span>
                </div>
            @endforeach
        @endforeach
    </details>

    {{-- Double confirmation for skipping a high priority item --}}
    @if ($confirm)
        <x-modal close="cancelConfirm">
            <div class="flex flex-col gap-2 p-6">
                <div class="h2 text-pretty">
                    {{ $confirm['step'] === 1 ? 'Skip a high priority item?' : 'Are you really sure?' }}
                </div>
                <div class="text-pretty text-text2">
                    @if ($confirm['step'] === 1)
                        “{{ $confirm['name'] }}” is high priority and needs an update today. Skip only if there is truly nothing to report.
                    @else
                        This skip is logged in the history as a high priority skip, and “{{ $confirm['name'] }}” disappears from today's Daily.
                    @endif
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                <button type="button" wire:click="cancelConfirm" class="btn-secondary">Go back</button>
                <button type="button" wire:click="advanceConfirm" class="btn-primary">{{ $confirm['step'] === 1 ? 'Skip anyway' : 'Yes, skip anyway' }}</button>
            </div>
        </x-modal>
    @endif
</div>
