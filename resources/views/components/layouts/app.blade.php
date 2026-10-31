@php
    $me = auth()->user();
    // Client-portal users get no navigation, only the project shared with them.
    $tabs = $me->isStaff() ? array_filter([
        'today' => ['Today', 'inbox', route('today')],
        'projects' => ['Projects', 'grid', route('projects.index')],
        'products' => ['Products', 'package-check', route('products.index')],
        'clients' => $me->canManageClients() ? ['Clients', 'users', route('clients.index')] : null,
        'tasks' => ['Tasks', 'tasks', route('tasks')],
    ]) : [];
    // Secondary destinations: in the account menu on desktop, under "More" on mobile.
    $extra = $me->isStaff() ? array_filter([
        'archived' => ['Archived', 'archive', route('archived')],
        'team' => $me->isOwner() ? ['Team', 'users', route('team')] : null,
    ]) : [];
    $primaryKeys = array_slice(array_keys($tabs), 0, 4);
    $moreTabs = array_diff_key($tabs, array_flip($primaryKeys)) + $extra;
    $active ??= '';
    $initial = strtoupper(mb_substr($me->email, 0, 1));
    $homeUrl = $me->isStaff() ? route('projects.index') : url('/');
@endphp
<x-layouts.base :title="$title ?? 'Pulse'">
<div class="min-h-screen bg-bg text-text">

    {{-- Desktop header --}}
    <header class="sticky top-0 z-30 hidden border-b border-border bg-bg md:block">
        <div class="mx-auto grid h-16 max-w-[1200px] grid-cols-[1fr_auto_1fr] items-center gap-4 px-6">
            <a href="{{ $homeUrl }}" class="justify-self-start text-[22px] leading-8 font-bold tracking-[-0.01em] text-text hover:no-underline">Pulse</a>
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
    @if ($tabs)
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-border bg-card pb-[env(safe-area-inset-bottom)] md:hidden">
        @foreach (array_intersect_key($tabs, array_flip($primaryKeys)) as $key => [$label, $icon, $url])
            <a href="{{ $url }}" @class(['flex h-16 flex-col items-center justify-center gap-1 font-semibold transition-colors hover:no-underline',
                'text-accent-fg' => $active === $key, 'text-text2' => $active !== $key])>
                <x-icon :name="$icon" /><span>{{ $label }}</span>
            </a>
        @endforeach
        <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
            <button type="button" x-on:click="open = !open" @class(['flex h-16 w-full flex-col items-center justify-center gap-1 border-0 bg-transparent p-0 font-semibold transition-colors',
                'text-accent-fg' => array_key_exists($active, $moreTabs), 'text-text2' => ! array_key_exists($active, $moreTabs)])>
                <x-icon name="more" /><span>More</span>
            </button>
            <div x-show="open" x-cloak x-on:click.outside="open = false" class="menu right-2 bottom-[68px] w-52">
                @foreach ($moreTabs as [$label, $icon, $url])
                    <a href="{{ $url }}" class="menu-item hover:no-underline"><x-icon :name="$icon" />{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </nav>
    @endif
    @if ($me->isStaff())
    <button type="button" title="Add update" x-data
        x-on:click="Livewire.dispatch('open-update', { projectId: {{ $fabProject ?? 'null' }} })"
        class="fixed right-4 bottom-20 z-31 flex size-14 items-center justify-center rounded-full border-0 bg-accent text-white md:hidden">
        <x-icon name="plus" />
    </button>
    @endif

    {{-- Toast --}}
    <div x-data="{ msg: @js(session('toast', '')), t: null }" x-init="if (msg) t = setTimeout(() => msg = '', 2400)" x-on:toast.window="msg = $event.detail.message; clearTimeout(t); t = setTimeout(() => msg = '', 2400)"
        x-show="msg" x-cloak x-text="msg"
        class="fixed bottom-40 left-1/2 z-80 max-w-[calc(100vw-32px)] -translate-x-1/2 overflow-hidden rounded-lg bg-text px-4 py-2 font-medium text-ellipsis whitespace-nowrap text-bg md:bottom-6"
        style="animation: pRise 180ms ease-out"></div>

    @if ($me->isStaff())<livewire:add-update />@endif
</div>
</x-layouts.base>
