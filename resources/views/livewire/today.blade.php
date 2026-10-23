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

            @foreach ($section['rows'] as [$project, $meta])
                <div wire:key="{{ $section['key'] }}-{{ $project->id }}" class="flex min-h-16 flex-wrap items-center gap-x-4 gap-y-2 border-t border-border py-2 pr-2 pl-4">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
                        <a href="{{ route('projects.show', $project) }}" class="text-base leading-6 font-semibold text-text hover:text-accent-fg hover:no-underline">{{ $project->name }}</a>
                        <x-move-pill :project="$project" />
                    </div>
                    <div class="min-w-0 flex-[1_1_220px] truncate text-text2">{{ $project->statusLine() }}</div>
                    <div class="ml-auto flex items-center gap-2">
                        <span class="whitespace-nowrap text-text2">{{ $meta }}</span>
                        @if ($section['nudge'])
                            <button type="button" wire:click="nudge({{ $project->id }})" class="btn-ghost">Nudge</button>
                        @endif
                        <button type="button" title="Add update" class="icon-btn border border-border"
                            x-data x-on:click="Livewire.dispatch('open-update', { projectId: {{ $project->id }} })">
                            <x-icon name="plus" />
                        </button>
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach
</div>
