<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\On;

/** Re-render when the global Add Update panel posts an entry. */
trait RefreshesOnEntry
{
    #[On('entry-posted')]
    public function refreshAfterEntry(): void
    {
        // Intentionally empty: handling the event re-renders the component.
    }
}
