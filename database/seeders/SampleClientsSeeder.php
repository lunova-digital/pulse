<?php

namespace Database\Seeders;

use App\Enums\ProjectKind;
use App\Models\Client;
use App\Models\PipelineStage;
use App\Models\Project;
use Illuminate\Database\Seeder;

/** Demo clients with follow-ups, and two of the sample projects marked as products. Not run in production. */
class SampleClientsSeeder extends Seeder
{
    public function run(): void
    {
        Project::active()->orderBy('id')->limit(2)->get()->each(fn (Project $p) => $p->update(['kind' => ProjectKind::Product]));

        $rows = [
            ['Brightside Dental', 'Lead', today(), false, 'Asked about a booking site; send the intro deck.'],
            ['Harbor Logistics', 'Proposal', today()->subDays(2), false, 'Proposal sent. Waiting on their finance team.'],
            ['Nova Fitness', 'Meeting', today()->addDays(3)->setTime(15, 0), true, 'Demo booked for Thursday.'],
            ['Pine & Co', 'Contacted', null, false, 'Replied once, no date agreed yet.'],
        ];

        foreach ($rows as [$name, $stage, $follow, $timed, $note]) {
            $client = Client::start(['name' => $name, 'stage_id' => PipelineStage::where('name', $stage)->value('id')]);
            $client->logUpdate(\App\Enums\ClientEntryType::Note, $note, $follow, $timed, noFollowUp: $follow === null);
        }
    }
}
