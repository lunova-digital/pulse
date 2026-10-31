<div class="flex flex-col gap-6" style="animation: pFade 200ms">
    <div>
        <h1 class="h1">Today</h1>
        <div class="text-text2">{{ now()->format('l, j M Y') }}</div>
    </div>

    @foreach ($sections as $section)
        <section class="card overflow-hidden" wire:key="section-{{ $section['key'] }}">
            <div class="flex items-center gap-2 p-4">
                <h2 class="h2">{{ $section['title'] }}</h2>
                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-lg bg-hover px-2 font-semibold text-text2">{{ $section['rows']->count() }}</span>
            </div>

            @if ($section['rows']->isEmpty())
                <x-joy>{{ $section['empty'] }}</x-joy>
            @endif

            @foreach ($section['rows'] as $task)
                <div wire:key="{{ $section['key'] }}-{{ $task->id }}" class="flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 border-t border-border py-2 pr-2 pl-4">
                    <div class="min-w-0 flex-[1_1_220px]">
                        <div class="text-pretty font-semibold">{{ $task->title }}</div>
                        @if ($task->project)
                            <a href="{{ route('projects.show', $task->project) }}" class="text-text2">{{ $task->project->name }}</a>
                        @else
                            <span class="text-text2">No project</span>
                        @endif
                    </div>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        @if ($task->is_high)<span class="tag-red">High</span>@endif
                        <span class="tag-gray">{{ $task->status->label() }}</span>
                        @if ($task->due_date)
                            <span @class(['whitespace-nowrap', 'text-red' => $task->isOverdue(), 'text-text2' => ! $task->isOverdue()])>{{ \App\Support\Fmt::due($task->due_date, false) }}</span>
                        @endif
                        @if (! $task->isInProgress())
                            <button type="button" wire:click="start({{ $task->id }})" class="btn-ghost">Start</button>
                        @endif
                        <button type="button" wire:click="done({{ $task->id }})" class="btn-secondary min-h-9 px-3 py-1">Done</button>
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach
</div>
