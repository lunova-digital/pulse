{{--
    Overlay used by every dialog. `panel` = right side panel on desktop, `dialog` = centered on desktop.
    On mobile both are bottom sheets. Call $wire.<close>() on Escape / scrim click.
--}}
@props(['variant' => 'dialog', 'title' => null, 'close' => 'close'])
<div x-data x-on:keydown.escape.window="$wire.{{ $close }}()">
    <div wire:click="{{ $close }}" class="fixed inset-0 z-60 bg-scrim" style="animation: pFade 150ms"></div>
    <div role="dialog" aria-modal="true" @class([
        'fixed z-61 flex flex-col overflow-hidden border border-border bg-card',
        'inset-x-0 bottom-0 max-h-[90vh] animate-[pSlideU_200ms_ease-out] rounded-t-xl',
        'md:inset-x-auto md:inset-y-0 md:right-0 md:max-h-screen md:w-[min(440px,100vw)] md:animate-[pSlideR_200ms_ease-out] md:rounded-none' => $variant === 'panel',
        'md:inset-auto md:top-1/2 md:left-1/2 md:max-h-[calc(100vh-64px)] md:w-[min(520px,calc(100vw-48px))] md:-translate-x-1/2 md:-translate-y-1/2 md:animate-[pFade_150ms_ease-out] md:rounded-xl' => $variant === 'dialog',
    ])>
        @if ($title)
            <div class="flex items-center gap-2 border-b border-border py-2 pr-2 pl-6">
                <div class="h2 flex-1">{{ $title }}</div>
                <button type="button" wire:click="{{ $close }}" title="Close" class="icon-btn"><x-icon name="x" /></button>
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
