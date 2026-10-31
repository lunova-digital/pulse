<?php

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\MilestoneState;
use App\Enums\WhoseMove;
use App\Exceptions\ImmutableTimelineEntry;
use App\Exceptions\ProjectCannotBeDeleted;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimelineEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProjectLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_projects_get_default_milestones_with_first_current(): void
    {
        $project = Project::factory()->create();

        $this->assertSame(
            ['SRS', 'Design', 'Development', 'Testing', 'Delivery', 'Maintenance'],
            $project->milestones->pluck('title')->all(),
        );
        $this->assertSame(MilestoneState::Current, $project->milestones[0]->state);
        $this->assertSame(MilestoneState::Upcoming, $project->milestones[1]->state);
    }

    public function test_archive_without_reason_fails(): void
    {
        $project = Project::factory()->create();

        foreach (['', '   '] as $reason) {
            try {
                $project->archive($reason);
                $this->fail('Archiving without a reason should throw.');
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertFalse($project->fresh()->isArchived());
        $this->assertSame(0, $project->entries()->count());
    }

    public function test_archive_sets_timestamp_and_adds_entry_with_reason(): void
    {
        $project = Project::factory()->create(['is_pinned' => true]);

        $project->archive('Client paused budget.');

        $project->refresh();
        $this->assertTrue($project->isArchived());
        $this->assertFalse($project->is_pinned);
        $this->assertSame(EntryType::Archived, $project->entries->first()->type);
        $this->assertSame('Client paused budget.', $project->entries->first()->note);
        $this->assertSame('Client paused budget.', $project->archiveReason());
        $this->assertSame(1, Project::archived()->count());
        $this->assertSame(0, Project::active()->count());
    }

    public function test_reopen_requires_reason_and_adds_entry(): void
    {
        $project = Project::factory()->archived()->create(['whose_move' => WhoseMove::None]);

        try {
            $project->reopen(' ');
            $this->fail('Reopening without a reason should throw.');
        } catch (InvalidArgumentException) {
        }
        $this->assertTrue($project->fresh()->isArchived());

        $project->reopen('Client is back.');

        $project->refresh();
        $this->assertFalse($project->isArchived());
        $entry = $project->entries->first();
        $this->assertSame(EntryType::Reopened, $entry->type);
        $this->assertSame('Client is back.', $entry->note);
        $this->assertSame(WhoseMove::Us, $project->whose_move);
    }

    public function test_deleting_a_project_throws(): void
    {
        $project = Project::factory()->create();

        $this->expectException(ProjectCannotBeDeleted::class);

        try {
            $project->delete();
        } finally {
            $this->assertDatabaseHas('projects', ['id' => $project->id]);
        }
    }

    public function test_posting_an_entry_changes_whose_move_and_resets_the_timer(): void
    {
        $this->travelTo(now()->subDays(3));
        $project = Project::factory()->create(['whose_move' => WhoseMove::Us]);
        $this->travelBack();

        $project->postEntry(EntryType::Call, 'Called client', 'Waiting for logo', WhoseMove::Client);

        $project->refresh();
        $this->assertSame(WhoseMove::Client, $project->whose_move);
        $this->assertTrue($project->whose_move_since->isToday());
        $this->assertTrue($project->last_activity_at->isToday());
        $this->assertSame(WhoseMove::Client, $project->entries->first()->whose_move);
    }

    public function test_posting_with_same_whose_move_keeps_the_timer_but_bumps_activity(): void
    {
        $this->travelTo(now()->subDays(3));
        $project = Project::factory()->create(['whose_move' => WhoseMove::Us]);
        $since = $project->whose_move_since->copy();
        $this->travelBack();

        $project->postEntry(EntryType::Note, 'Note', null, WhoseMove::Us);

        $project->refresh();
        $this->assertTrue($project->whose_move_since->equalTo($since));
        $this->assertTrue($project->last_activity_at->isToday());
    }

    public function test_archived_projects_reject_new_entries(): void
    {
        $project = Project::factory()->archived()->create();

        $this->expectException(InvalidArgumentException::class);
        $project->postEntry(EntryType::Note, 'Nope');
    }

    public function test_timeline_entries_are_append_only(): void
    {
        $entry = Project::factory()->create()->postEntry(EntryType::Note, 'First');

        try {
            $entry->update(['note' => 'changed']);
            $this->fail('Editing an entry should throw.');
        } catch (ImmutableTimelineEntry) {
        }

        $this->expectException(ImmutableTimelineEntry::class);
        $entry->delete();
    }

    public function test_scopes(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $stale = Project::factory()->create(['last_activity_at' => now()->subDays(5)]);
        $fresh = Project::factory()->create(['last_activity_at' => now()->subDays(4)]);
        $us = Project::factory()->waitingOn(WhoseMove::Us)->create();
        $client = Project::factory()->waitingOn(WhoseMove::Client)->create();
        $soon = Project::factory()->create(['next_meeting_at' => now()->addDays(3)->setTime(23, 0)]);
        $later = Project::factory()->create(['next_meeting_at' => now()->addDays(4)->setTime(1, 0)]);
        $today = Project::factory()->create(['next_meeting_at' => now()->setTime(8, 0)]);

        $this->assertEqualsCanonicalizing([$stale->id], Project::stale()->pluck('id')->all());
        $this->assertContains($us->id, Project::waitingOnUs()->pluck('id')->all());
        $this->assertNotContains($client->id, Project::waitingOnUs()->pluck('id')->all());
        $this->assertContains($client->id, Project::waitingOnClient()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$soon->id, $today->id], Project::meetingsSoon()->pluck('id')->all());
        $this->assertNotContains($later->id, Project::meetingsSoon()->pluck('id')->all());
        $this->assertTrue($stale->isStale());
        $this->assertFalse($fresh->isStale());
    }

    public function test_status_label_suggestions_are_distinct(): void
    {
        $project = Project::factory()->create();
        $project->postEntry(EntryType::Call, 'Design v2 sent');
        $project->postEntry(EntryType::Note, 'Design v2 sent');
        $project->postEntry(EntryType::Note, 'Contract signed');
        $project->archive('Done');

        $this->assertSame(['Contract signed', 'Design v2 sent'], Project::statusLabelSuggestions());
    }

    public function test_meeting_not_logged(): void
    {
        $project = Project::factory()->create(['next_meeting_at' => now()->subDay()]);
        $this->assertTrue($project->meetingNotLogged());

        $project->postEntry(EntryType::Meeting, 'Sync');
        $this->assertFalse($project->meetingNotLogged());
    }

    public function test_completing_a_linked_task_logs_a_note_on_the_project(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Send invoice']);
        $loose = Task::factory()->create();

        $task->complete();
        $loose->complete();

        $this->assertTrue($task->fresh()->isDone());
        $entry = $project->entries()->first();
        $this->assertSame(EntryType::Note, $entry->type);
        $this->assertStringContainsString('Send invoice', $entry->note);
        $this->assertSame(1, TimelineEntry::count());
    }

    public function test_roadmap_change_of_current_milestone_is_logged(): void
    {
        $project = Project::factory()->create();

        $project->saveRoadmap(['Plan', 'Build', 'Ship'], 1);

        $this->assertSame(['Plan', 'Build', 'Ship'], $project->milestones()->pluck('title')->all());
        $this->assertSame('Roadmap: Build', $project->entries()->first()->status_label);
    }

    public function test_seeder_builds_the_sample_data(): void
    {
        config(['pulse.email' => 'a@b.test', 'pulse.password' => 'secret-pass']);

        $this->seed();

        // 8 sample + 1 project started from the converted demo client.
        $this->assertSame(9, Project::active()->count());
        $this->assertSame(2, Project::archived()->count());
        Project::whereNull('client_id')->withCount('entries')->get()->each(
            fn ($p) => $this->assertTrue($p->entries_count >= 4 && $p->entries_count <= 8),
        );
        $this->assertDatabaseHas('users', ['email' => 'a@b.test']);

        // Demo data for the daily check-in, clients and team features.
        $this->assertGreaterThanOrEqual(2, Project::where('kind', 'product')->count());
        $this->assertGreaterThanOrEqual(2, Project::priorityOn(today())->count());
        $this->assertGreaterThanOrEqual(1, \App\Models\DailySkip::where('was_high', true)->count());
        $this->assertGreaterThanOrEqual(1, \App\Models\Task::where('is_high', true)->count());
        $this->assertSame(1, \App\Models\Client::whereHas('projects')->count());
        foreach (['member', 'sales', 'client', 'former'] as $who) {
            $this->assertDatabaseHas('users', ['email' => "{$who}@pulse.test"]);
        }
        $this->assertFalse(\App\Models\User::where('email', 'former@pulse.test')->value('is_active'));
    }
}
