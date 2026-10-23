{{-- Light/dark toggle; the choice is stored in localStorage (see layouts/base). --}}
@props(['withLabel' => false])
<button type="button"
    x-data="{ theme: document.documentElement.dataset.theme }"
    x-on:click="theme = theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = theme; try { localStorage.setItem('pulse-theme', theme) } catch (e) {}"
    title="Toggle light/dark"
    {{ $attributes->class($withLabel ? 'menu-item' : 'icon-btn') }}>
    <span x-show="theme !== 'dark'"><x-icon name="moon" /></span>
    <span x-show="theme === 'dark'" x-cloak><x-icon name="sun" /></span>
    @if ($withLabel)<span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'">Dark mode</span>@endif
</button>
