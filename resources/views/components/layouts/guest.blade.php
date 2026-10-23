<x-layouts.base :title="$title ?? 'Log in'">
    <div class="flex min-h-screen items-center justify-center p-6">
        {{ $slot }}
    </div>
</x-layouts.base>
