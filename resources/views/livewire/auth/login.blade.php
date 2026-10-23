<form wire:submit="login" class="flex w-full max-w-[400px] flex-col gap-6 rounded-xl border border-border bg-card p-8" style="animation: pFade 200ms">
    <div class="flex flex-col gap-2">
        <div class="text-[22px] leading-8 font-bold tracking-[-0.01em]">Pulse</div>
        <div class="text-text2">Your daily project status, without the notebook.</div>
    </div>
    <div class="flex flex-col gap-4">
        <label class="flex flex-col gap-2">
            <span class="font-semibold">Email</span>
            <input type="email" wire:model="email" placeholder="you@studio.com" autocomplete="username" autofocus class="field">
            @error('email')<span class="text-red">{{ $message }}</span>@enderror
        </label>
        <label class="flex flex-col gap-2">
            <span class="font-semibold">Password</span>
            <input type="password" wire:model="password" placeholder="••••••••" autocomplete="current-password" class="field">
            @error('password')<span class="text-red">{{ $message }}</span>@enderror
        </label>
    </div>
    <button type="submit" class="btn-primary w-full">Log in</button>
</form>
