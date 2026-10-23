<?php

namespace App\Livewire;

use App\Models\Project;
use Livewire\Component;

class Archived extends Component
{
    public function render()
    {
        return view('livewire.archived', [
            'projects' => Project::archived()->orderByDesc('archived_at')->get(),
        ])->layoutData(['active' => 'archived', 'title' => 'Archived']);
    }
}
