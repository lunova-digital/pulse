<div x-show="open" x-cloak x-on:click.outside="open = false" class="menu top-[calc(100%+6px)] right-0 w-64 md:top-[calc(100%+10px)]">
    <div class="mb-2 border-b border-border px-2 pt-2 pb-4">
        <div class="font-semibold">Signed in</div>
        <div class="truncate text-text2">{{ auth()->user()->email }}</div>
    </div>
    @foreach ($extra as [$label, $icon, $url])
        <a href="{{ $url }}" class="menu-item hover:no-underline"><x-icon :name="$icon" />{{ $label }}</a>
    @endforeach
    <x-theme-toggle :with-label="true" />
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="menu-item"><x-icon name="logout" />Log out</button>
    </form>
</div>
