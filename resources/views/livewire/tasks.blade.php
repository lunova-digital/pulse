<div class="mx-auto flex max-w-[800px] flex-col gap-6" style="animation: pFade 200ms">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="h1 flex-1">Tasks</h1>
        @if ($tab === 'open' && $tasks->isNotEmpty())
            <button type="button" wire:click="toggleSelecting" @class(['btn-secondary', 'border-accent' => $selecting])>{{ $selecting ? 'Done' : 'Select' }}</button>
        @endif
    </div>

    <div @class(['rounded-lg border px-4 py-2', 'border-border bg-hover text-text2' => $untouched === 0, 'border-accent bg-accent-t text-accent-fg' => $untouched > 0])>
        @if ($untouched > 0)
            <b>{{ $untouched }}</b> {{ $untouched === 1 ? 'task' : 'tasks' }} not touched today. Finish each one or skip it; the bot keeps reminding until they are cleared.
        @else
            Every task is touched today.
        @endif
    </div>

    @if ($selecting)
        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-hover px-4 py-2">
            <span class="flex-1 text-text2">{{ count($selected) }} selected. Skipped tasks come back tomorrow.</span>
            <button type="button" wire:click="skipSelected" @disabled(! $selected) class="btn-primary">Skip for today</button>
        </div>
    @endif

    <form wire:submit="add" class="card flex flex-wrap gap-2 p-4">
        <input wire:model="title" placeholder="+ Add task" class="field min-w-0 flex-[1_1_240px]" aria-label="Task title">
        <div class="min-w-0 flex-[1_1_160px]">
            <select wire:model="projectId" class="field" aria-label="Linked project">
                <option value="">No project</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <input type="date" wire:model="due" class="field min-w-0 flex-[0_1_160px] px-2" aria-label="Due date">
        <select wire:model="repeat" class="field min-w-0 flex-[0_1_150px]" aria-label="Repeat">
            <option value="">Does not repeat</option>
            @foreach (\App\Enums\Recurrence::cases() as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
        </select>
        <label class="flex h-11 items-center gap-2 text-text2"><input type="checkbox" wire:model="high" class="size-4 accent-accent">High priority</label>
        <button type="submit" x-data x-bind:disabled="!$wire.title.trim()" class="btn-primary">Add</button>
        @error('title')<div class="w-full text-red">{{ $message }}</div>@enderror
    </form>

    <div class="flex gap-2 border-b border-border">
        @foreach (['open' => 'Open · '.$openCount, 'done' => 'Done · '.$doneCount] as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" @class(['-mb-px h-11 border-0 border-b-2 bg-transparent px-4 font-semibold transition-colors',
                'border-accent text-accent-fg' => $tab === $key, 'border-transparent text-text2' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        @if ($tasks->isEmpty())
            <x-joy>{{ $tab === 'open' ? 'All tasks done.' : 'No finished tasks yet.' }}</x-joy>
        @endif
        @foreach ($tasks as $task)
            @php($later = ! $task->isDone() && $task->due_date && $task->due_date->gt(today()))
            @if ($tab === 'open' && $later && ! ($prevLater ?? false))
                <div class="-mt-px border-t border-border bg-hover px-4 py-2 font-semibold text-text2">Later</div>
            @endif
            @php($prevLater = $later)
            <div wire:key="task-{{ $task->id }}" class="-mt-px flex min-h-[60px] flex-wrap items-center gap-2 border-t border-border py-2 pr-4 pl-2">
                @if ($selecting && ! $task->is_high && ! $later)
                    <input type="checkbox" wire:model.live="selected" value="{{ $task->id }}" aria-label="Select {{ $task->title }}" class="ml-2 size-5 shrink-0 accent-accent">
                @endif
                <button type="button" wire:click="toggle({{ $task->id }})" title="{{ $task->isDone() ? 'Mark as open' : 'Mark as done' }}"
                    class="flex size-11 shrink-0 items-center justify-center border-0 bg-transparent p-0">
                    <span @class(['flex size-5 items-center justify-center rounded-md border-2 text-white transition-all',
                        'border-green bg-green' => $task->isDone(), 'border-border bg-transparent' => ! $task->isDone()])>
                        @if ($task->isDone())<x-icon name="check" :size="14" :stroke="3" />@endif
                    </span>
                </button>
                <div @class(['min-w-0 flex-[1_1_200px]', 'text-text2 line-through' => $task->isDone(), 'text-text' => ! $task->isDone()])>{{ $task->title }}</div>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    @unless ($task->isDone())
                        <button type="button" wire:click="toggleHigh({{ $task->id }})" title="Toggle high priority"
                            @class(['inline-flex h-7 items-center rounded-lg border px-2 font-medium transition-colors',
                                'border-red bg-red-t text-red' => $task->is_high, 'border-border text-text2 hover:text-text' => ! $task->is_high])>High</button>
                    @endunless
                    @if ($task->recurrence)
                        <span class="tag-gray" title="Skipping moves it to its next day">{{ $task->recurrence->label() }}</span>
                    @endif
                    @if ($task->project)
                        <a href="{{ route('projects.show', $task->project) }}" class="inline-flex h-7 items-center rounded-lg border border-border bg-bg px-2 font-medium text-text2 hover:text-text hover:no-underline">{{ $task->project->name }}</a>
                    @endif
                    @if (! $task->isDone() && ! $later)
                        <button type="button" wire:click="skip({{ $task->id }})" title="Nothing to do on it today"
                            class="btn-secondary min-h-9 px-3 py-1">Skip today</button>
                    @endif
                    @if ($task->due_date)
                        <span @class(['whitespace-nowrap', 'text-red' => $task->isOverdue(), 'text-text2' => ! $task->isOverdue()])>{{ \App\Support\Fmt::due($task->due_date, $task->isDone()) }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Skip history, one row per task per day: collapsed by default --}}
    <details class="card overflow-hidden" wire:key="task-skip-history">
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
                <div wire:key="task-skip-{{ $skip->id }}" class="flex flex-wrap items-center gap-2 border-t border-border px-4 py-2">
                    <span class="min-w-0 flex-1 truncate">{{ $skip->task?->title }}</span>
                    @if ($skip->task?->recurrence)<span class="tag-gray">{{ $skip->task->recurrence->label() }}</span>@endif
                    @if ($skip->was_high)<span class="tag-gray">Was high</span>@endif
                    <span class="text-text2">{{ $skip->user?->name }}</span>
                </div>
            @endforeach
        @endforeach
    </details>
</div>
