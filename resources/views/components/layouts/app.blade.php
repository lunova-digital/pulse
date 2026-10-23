@php
    $tabs = [
        'today' => ['Today', 'inbox', route('today')],
        'projects' => ['Projects', 'grid', route('projects.index')],
        'tasks' => ['Tasks', 'tasks', route('tasks')],
        'archived' => ['Archived', 'archive', route('archived')],
    ];
    $active ??= '';
    $initial = strtoupper(mb_substr(auth()->user()->email, 0, 1));
@endphp
<x-layouts.base :title="$title ?? 'Pulse'">
<div class="min-h-screen bg-bg text-text">

    {{-- Desktop header --}}
    <header class="sticky top-0 z-30 hidden border-b border-border bg-bg md:block">
        <div class="mx-auto grid h-16 max-w-[1200px] grid-cols-[1fr_auto_1fr] items-center gap-4 px-6">
            <a href="{{ route('today') }}" class="justify-self-start text-[22px] leading-8 font-bold tracking-[-0.01em] text-text hover:no-underline">Pulse</a>
            <nav class="flex h-16 gap-2">
                @foreach ($tabs as $key => [$label, , $url])
                    <a href="{{ $url }}" @class(['flex h-16 items-center border-b-2 px-4 font-semibold transition-colors hover:no-underline',
                        'border-accent text-accent-fg' => $active === $key,
                        'border-transparent text-text2' => $active !== $key])>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="relative flex items-center gap-2 justify-self-end" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                <x-theme-toggle />
                <button type="button" x-on:click="open = !open" title="Account" class="flex size-11 items-center justify-center border-0 bg-transparent p-0">
                    <span class="flex size-8 items-center justify-center rounded-full border border-border bg-hover font-semibold text-text">{{ $initial }}</span>
                </button>
                @include('components.layouts.account-menu')
            </div>
        </div>
    </header>

    {{-- Mobile header --}}
    <header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-border bg-bg pr-2 pl-4 md:hidden">
        <div class="flex-1 text-[22px] leading-8 font-bold tracking-[-0.01em]">Pulse</div>
        <x-theme-toggle />
        <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
            <button type="button" x-on:click="open = !open" title="Account" class="flex size-11 items-center justify-center border-0 bg-transparent p-0">
                <span class="flex size-8 items-center justify-center rounded-full border border-border bg-hover font-semibold text-text">{{ $initial }}</span>
            </button>
            @include('components.layouts.account-menu')
        </div>
    </header>

    <main class="mx-auto max-w-[1200px] px-4 pt-4 pb-40 md:px-6 md:pt-8 md:pb-16">
        {{ $slot }}
    </main>

    {{-- Mobile bottom nav + floating "+ Update" --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-border bg-card pb-[env(safe-area-inset-bottom)] md:hidden">
        @foreach ($tabs as $key => [$label, $icon, $url])
            <a href="{{ $url }}" @class(['flex h-16 flex-col items-center justify-center gap-1 font-semibold transition-colors hover:no-underline',
                'text-accent-fg' => $active === $key, 'text-text2' => $active !== $key])>
                <x-icon :name="$icon" /><span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    <button type="button" title="Add update" x-data
        x-on:click="Livewire.dispatch('open-update', { projectId: {{ $fabProject ?? 'null' }} })"
        class="fixed right-4 bottom-20 z-31 flex size-14 items-center justify-center rounded-full border-0 bg-accent text-white md:hidden">
        <x-icon name="plus" />
    </button>

    {{-- Toast --}}
    <div x-data="{ msg: @js(session('toast', '')), t: null }" x-init="if (msg) t = setTimeout(() => msg = '', 2400)" x-on:toast.window="msg = $event.detail.message; clearTimeout(t); t = setTimeout(() => msg = '', 2400)"
        x-show="msg" x-cloak x-text="msg"
        class="fixed bottom-40 left-1/2 z-80 max-w-[calc(100vw-32px)] -translate-x-1/2 overflow-hidden rounded-lg bg-text px-4 py-2 font-medium text-ellipsis whitespace-nowrap text-bg md:bottom-6"
        style="animation: pRise 180ms ease-out"></div>

    <livewire:add-update />
</div>
</x-layouts.base>
