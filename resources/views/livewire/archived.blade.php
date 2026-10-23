<div class="flex flex-col gap-6" style="animation: pFade 200ms">
    <div>
        <h1 class="h1">Archived</h1>
        <div class="text-text2">Archived projects keep their full timeline. Open one to reopen it.</div>
    </div>

    @if ($projects->isEmpty())
        <div class="card"><x-joy>Nothing archived.</x-joy></div>
    @endif

    <div class="grid grid-cols-1 gap-4 min-[600px]:grid-cols-2 lg:grid-cols-4">
        @foreach ($projects as $project)
            <a wire:key="archived-{{ $project->id }}" href="{{ route('projects.show', $project) }}"
                class="flex min-h-[200px] min-w-0 flex-col gap-2 rounded-xl border border-border bg-bg p-4 transition-colors hover:border-text2 hover:no-underline">
                <div class="text-base leading-6 font-semibold text-text2">{{ $project->name }}</div>
                <div><x-move-pill :project="$project" /></div>
                <div class="line-clamp-3 text-pretty text-text2">{{ $project->archiveReason() }}</div>
                <div class="mt-auto pt-2 text-text2">Archived {{ \App\Support\Fmt::date($project->archived_at) }}</div>
            </a>
        @endforeach
    </div>
</div>
