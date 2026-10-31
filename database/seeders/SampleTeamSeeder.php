<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Client;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo people and daily-check-in data for local testing. Never run in production.
 * Every demo account uses the password "password".
 */
class SampleTeamSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::active()->orderBy('id')->get();
        $owner = User::where('role', Role::Owner)->first();

        // Team member: three assigned items, no Clients tab.
        $member = User::updateOrCreate(['email' => 'member@pulse.test'], [
            'name' => 'Mina Member', 'password' => 'password', 'role' => Role::Member, 'can_manage_clients' => false, 'is_active' => true,
        ]);
        $member->projects()->sync($projects->take(3)->pluck('id'));

        // Team member who can also use Clients.
        $sales = User::updateOrCreate(['email' => 'sales@pulse.test'], [
            'name' => 'Sam Sales', 'password' => 'password', 'role' => Role::Member, 'can_manage_clients' => true, 'is_active' => true,
        ]);
        $sales->projects()->sync($projects->skip(3)->take(2)->pluck('id'));

        // Client-portal user: read-only on one project, which gets one internal note they must not see.
        $shared = $projects->get(3);
        $portal = User::updateOrCreate(['email' => 'client@pulse.test'], [
            'name' => 'Client Co', 'password' => 'password', 'role' => Role::Client, 'can_manage_clients' => false, 'is_active' => true,
        ]);
        $portal->projects()->sync([$shared->id]);
        $shared->postEntry(\App\Enums\EntryType::Note, 'Internal: budget tight', 'Team-only note, hidden from the client portal.', internal: true);

        // Deactivated user, to test that sign-in is blocked.
        User::updateOrCreate(['email' => 'former@pulse.test'], [
            'name' => 'Former Member', 'password' => 'password', 'role' => Role::Member, 'is_active' => false,
        ]);

        // Today's priority (two unchecked, one already updated) and tomorrow's.
        $projects->get(5)?->pinForDate(today());
        $projects->get(6)?->pinForDate(today());
        $projects->get(7)?->pinForDate(today());
        $projects->get(7)?->postEntry(\App\Enums\EntryType::Note, 'Checked in', 'Update posted today, so this one shows as done.');
        $projects->get(4)?->pinForDate(today()->addDay());

        // A high priority task.
        Task::whereNull('completed_at')->orderBy('id')->first()?->update(['is_high' => true, 'user_id' => $owner?->id]);

        // Skip history across recent days, including a high priority skip.
        foreach ([[1, 4, false], [1, 5, true], [2, 4, false], [3, 6, false]] as [$daysAgo, $index, $high]) {
            $project = $projects->get($index);
            if (! $project) {
                continue;
            }
            DailySkip::create([
                'project_id' => $project->id, 'user_id' => ($index % 2 ? $member : $owner)?->id,
                'skipped_on' => today()->subDays($daysAgo)->toDateString(), 'was_high' => $high,
                'created_at' => today()->subDays($daysAgo)->setTime(17, 30),
            ]);
        }

        // A client that already converted into a project.
        $won = Client::start(['name' => 'Converted Clinic'], $owner);
        $won->startProject('Converted Clinic booking site', $owner);
    }
}
