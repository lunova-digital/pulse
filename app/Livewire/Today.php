<?php

namespace App\Livewire;

use App\Livewire\Concerns\RefreshesOnEntry;
use App\Models\Project;
use App\Support\Fmt;
use Livewire\Component;

class Today extends Component
{
    use RefreshesOnEntry;

    public function nudge(int $projectId): void
    {
        Project::active()->visibleTo(auth()->user())->findOrFail($projectId);

        $this->dispatch('open-update', projectId: $projectId, preset: [
            'type' => 'note',
            'label' => 'Nudged client',
            'note' => '',
        ]);
    }

    public function render()
    {
        $user = auth()->user();
        $base = fn () => Project::active()->visibleTo($user)->with('latestEntry');

        $sections = [
            [
                'key' => 'us', 'title' => 'Needs our action', 'empty' => 'Nothing waiting on us.', 'nudge' => false,
                'rows' => $base()->waitingOnUs()->orderBy('whose_move_since')->get()
                    ->map(fn (Project $p) => [$p, 'Waiting '.Fmt::duration($p->daysSince($p->whose_move_since))]),
            ],
            [
                'key' => 'meet', 'title' => 'Meetings in next 3 days', 'empty' => 'No meetings coming up.', 'nudge' => false,
                'rows' => $base()->meetingsSoon()->orderBy('next_meeting_at')->get()
                    ->map(fn (Project $p) => [$p, Fmt::relativeMeeting($p->next_meeting_at)]),
            ],
            [
                'key' => 'client', 'title' => 'Waiting on client', 'empty' => 'Nobody to chase.', 'nudge' => true,
                'rows' => $base()->waitingOnClient()->orderBy('whose_move_since')->get()
                    ->map(fn (Project $p) => [$p, 'Waiting '.Fmt::duration($p->daysSince($p->whose_move_since))]),
            ],
            [
                'key' => 'stale', 'title' => 'Stale', 'empty' => 'Every project is fresh.', 'nudge' => false,
                'rows' => $base()->stale()->orderBy('last_activity_at')->get()
                    ->map(fn (Project $p) => [$p, 'No update for '.$p->daysSince($p->last_activity_at).' days']),
            ],
        ];

        return view('livewire.today', ['sections' => $sections])
            ->layoutData(['active' => 'today', 'title' => 'Today']);
    }
}
