<div class="mx-auto flex max-w-[800px] flex-col gap-6" style="animation: pFade 200ms">
    <h1 class="h1">Tasks</h1>

    <form wire:submit="add" class="card flex flex-wrap gap-2 p-4">
        <input wire:model="title" placeholder="+ Add task" class="field min-w-0 flex-[1_1_240px]" aria-label="Task title">
        <div class="min-w-0 flex-[1_1_160px]">
            <select wire:model="projectId" class="field" aria-label="Linked project">
                <option value="">No project</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
        </div>
        <input type="date" wire:model="due" class="field min-w-0 flex-[0_1_160px] px-2" aria-label="Due date">
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
            <div wire:key="task-{{ $task->id }}" class="-mt-px flex min-h-[60px] flex-wrap items-center gap-2 border-t border-border py-2 pr-4 pl-2">
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
                    @if ($task->project)
                        <a href="{{ route('projects.show', $task->project) }}" class="inline-flex h-7 items-center rounded-lg border border-border bg-bg px-2 font-medium text-text2 hover:text-text hover:no-underline">{{ $task->project->name }}</a>
                    @endif
                    @if ($task->due_date)
                        <span @class(['whitespace-nowrap', 'text-red' => $task->isOverdue(), 'text-text2' => ! $task->isOverdue()])>{{ \App\Support\Fmt::due($task->due_date, $task->isDone()) }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
