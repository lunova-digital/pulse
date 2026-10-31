{{-- Tinted whose-move pill. Pass a project, or an explicit move (null = neutral/archived) + label. --}}
@props(['project' => null, 'move' => null, 'label' => null])
@php
    $hide = $project && ! $project->tracksWhoseMove();
    if ($project) {
        $archived = $project->isArchived();
        $move = $archived ? null : $project->whose_move;
        $label ??= $project->moveBadge();
    }
@endphp
@unless ($hide)
<span {{ $attributes->class(['pill', $move?->pill() ?? 'bg-gray-t text-text2']) }}>
    <span class="size-1.5 rounded-full {{ $move?->dot() ?? 'bg-gray' }}"></span>{{ $label }}
</span>
@endunless
