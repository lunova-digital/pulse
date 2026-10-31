<section class="card flex flex-col gap-4 p-4 md:p-6" x-data="{ drag: null, over: null }">
    <h2 class="h2">Tasks</h2>

    <form wire:submit="add" class="flex flex-wrap gap-2">
        <input wire:model="title" placeholder="+ Add task" class="field min-w-0 flex-[1_1_220px]" aria-label="Task title">
        <input type="date" wire:model="due" class="field min-w-0 flex-[0_1_160px] px-2" aria-label="Deadline">
        <label class="flex h-11 items-center gap-2 text-text2"><input type="checkbox" wire:model="high" class="size-4 accent-accent">High</label>
        <button type="submit" x-data x-bind:disabled="!$wire.title.trim()" class="btn-primary">Add</button>
        @error('title')<div class="w-full text-red">{{ $message }}</div>@enderror
    </form>

    @php($order = array_map(fn ($s) => $s->value, \App\Enums\TaskStatus::cases()))
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach (\App\Enums\TaskStatus::cases() as $status)
            @php($cards = $columns[$status->value])
            @php($index = array_search($status->value, $order))
            <div wire:key="col-{{ $status->value }}"
                x-on:dragover.prevent="over = '{{ $status->value }}'"
                x-on:dragleave="if (over === '{{ $status->value }}') over = null"
                x-on:drop.prevent="if (drag) { $wire.move(drag, '{{ $status->value }}') } drag = null; over = null"
                x-bind:class="over === '{{ $status->value }}' ? 'border-accent bg-accent-t' : 'border-border bg-hover'"
                class="flex min-h-32 flex-col gap-2 rounded-lg border p-2 transition-colors">
                <div class="flex items-center gap-2 px-2 py-1">
                    <h3 class="flex-1 font-semibold">{{ $status->label() }}</h3>
                    <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-lg bg-bg px-2 font-semibold text-text2">{{ $cards->count() }}</span>
                </div>

                @foreach ($cards as $task)
                    <div wire:key="task-{{ $task->id }}" draggable="true"
                        x-on:dragstart="drag = {{ $task->id }}; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', '{{ $task->id }}')"
                        x-on:dragend="drag = null; over = null"
                        class="flex cursor-grab flex-col gap-2 rounded-lg border border-border bg-bg p-3 active:cursor-grabbing">
                        <div class="flex items-start gap-2">
                            <div @class(['min-w-0 flex-1 text-pretty', 'text-text2 line-through' => $task->isDone()])>{{ $task->title }}</div>
                            @if ($task->is_high && ! $task->isDone())<span class="tag-red">High</span>@endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($task->isDone())
                                <span class="flex-1 text-text2">Done {{ \App\Support\Fmt::ago($task->completed_at) }}</span>
                            @else
                                <input type="date" value="{{ $task->due_date?->format('Y-m-d') }}" aria-label="Deadline for {{ $task->title }}"
                                    wire:change="setDue({{ $task->id }}, $event.target.value)"
                                    @class(['field min-h-9 w-auto flex-1 px-2 py-1', 'text-red' => $task->isOverdue()])>
                                <button type="button" wire:click="toggleHigh({{ $task->id }})" title="Toggle high priority"
                                    @class(['inline-flex h-9 items-center rounded-lg border px-2 font-medium transition-colors',
                                        'border-red bg-red-t text-red' => $task->is_high, 'border-border text-text2 hover:text-text' => ! $task->is_high])>High</button>
                            @endif
                            {{-- Touch screens cannot drag: step the task along the board instead. --}}
                            @if ($index > 0)
                                <button type="button" wire:click="move({{ $task->id }}, '{{ $order[$index - 1] }}')" title="Move to {{ \App\Enums\TaskStatus::from($order[$index - 1])->label() }}"
                                    class="icon-btn size-9 border border-border" aria-label="Move to {{ \App\Enums\TaskStatus::from($order[$index - 1])->label() }}">←</button>
                            @endif
                            @if ($index < count($order) - 1)
                                <button type="button" wire:click="move({{ $task->id }}, '{{ $order[$index + 1] }}')" title="Move to {{ \App\Enums\TaskStatus::from($order[$index + 1])->label() }}"
                                    class="icon-btn size-9 border border-border" aria-label="Move to {{ \App\Enums\TaskStatus::from($order[$index + 1])->label() }}">→</button>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($cards->isEmpty())
                    <div class="px-2 py-4 text-center text-text2">Drop tasks here</div>
                @endif
            </div>
        @endforeach
    </div>
</section>
