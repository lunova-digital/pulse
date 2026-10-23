{{-- Playful empty state from the design. --}}
<div class="flex flex-col items-center gap-2 px-4 pt-4 pb-8 text-text2">
    <div class="relative h-[88px] w-[72px]">
        @foreach ([[-40, 0], [0, .06], [40, .12]] as [$rot, $delay])
            <div class="absolute top-0.5 left-[35px] h-[50px] w-0.5" style="transform: rotate({{ $rot }}deg); transform-origin: 1px 50px">
                <div class="h-2 w-0.5 rounded-[1px] bg-text2" style="animation: pSpark 1.6s ease-out {{ $delay }}s infinite"></div>
            </div>
        @endforeach
        <div class="absolute top-8 left-4 size-10 rounded-full border-2 border-text2 bg-card" style="animation: pBounce 1.6s ease-in-out infinite; transform-origin: 50% 100%">
            <div class="absolute top-3 left-2.5 h-1.5 w-1 rounded-sm bg-text2" style="animation: pBlink 3.2s infinite"></div>
            <div class="absolute top-3 right-2.5 h-1.5 w-1 rounded-sm bg-text2" style="animation: pBlink 3.2s infinite"></div>
            <div class="absolute top-[21px] left-[11px] h-[7px] w-3.5 border-2 border-t-0 border-text2" style="border-radius: 0 0 10px 10px"></div>
        </div>
        <div class="absolute top-20 left-6 h-1 w-6 rounded-full bg-border" style="animation: pShadow 1.6s ease-in-out infinite"></div>
    </div>
    <div>{{ $slot }}</div>
</div>
