<?php

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Exceptions\HighPrioritySkipNotConfirmed;
use App\Exceptions\ImmutableRecord;
use App\Livewire\Daily;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_every_active_project_is_on_daily_by_default_and_archived_are_not(): void
    {
        Project::factory()->create(['name' => 'Alpha']);
        Project::factory()->archived()->create(['name' => 'Gone']);

        Livewire::test(Daily::class)->assertSee('Alpha')->assertDontSee('Gone');
        $this->get('/daily')->assertOk();
    }

    public function test_posting_an_update_checks_a_project_in(): void
    {
        $project = Project::factory()->create();

        $this->assertFalse($project->isCheckedInToday());
        $project->postEntry(EntryType::Note, 'Status', 'Did a thing');
        $this->assertTrue($project->fresh()->isCheckedInToday());
    }

    public function test_skipping_hides_the_item_for_today_and_logs_the_skip(): void
    {
        $project = Project::factory()->create(['name' => 'Quiet']);

        Livewire::test(Daily::class)->call('skip', 'project', $project->id);

        $this->assertTrue($project->fresh()->isSkippedToday());
        $this->assertTrue($project->fresh()->isCheckedInToday());
        $this->assertSame(0, $project->entries()->where('type', '!=', 'note')->count());
        $this->assertDatabaseHas('daily_skips', ['project_id' => $project->id, 'user_id' => $this->user->id, 'was_high' => false]);

        // Hidden from the active lists, visible in the (collapsed) history.
        Livewire::test(Daily::class)->assertSee('Skip history')->assertSee('Quiet');
        $this->assertStringNotContainsString('wire:key="other-'.$project->id.'"', Livewire::test(Daily::class)->html());
    }

    public function test_skipping_twice_in_a_day_logs_once(): void
    {
        $project = Project::factory()->create();

        $project->skipToday($this->user);
        $project->skipToday($this->user);

        $this->assertSame(1, DailySkip::count());
    }

    public function test_skip_is_a_check_in_only_for_that_day(): void
    {
        $project = Project::factory()->create();
        $project->skipToday($this->user);

        $this->travel(1)->day();

        $this->assertFalse($project->fresh()->isSkippedToday());
        Livewire::test(Daily::class)->assertSee($project->name);
    }

    public function test_priority_pinning_is_dated_and_expires(): void
    {
        $project = Project::factory()->create(['name' => 'Rocket']);

        Livewire::test(Daily::class)->call('setPriority', $project->id, 'tomorrow');
        $this->assertFalse($project->fresh()->isPriorityToday());
        Livewire::test(Daily::class)->assertSee("Tomorrow's priority", false);

        $this->travel(1)->day();
        $this->assertTrue($project->fresh()->isPriorityToday());

        $this->travel(1)->day();
        $this->assertFalse($project->fresh()->isPriorityToday());
    }

    public function test_high_priority_skip_needs_double_confirmation(): void
    {
        $project = Project::factory()->create(['name' => 'Urgent']);
        $project->pinForDate(today());

        $this->expectException(HighPrioritySkipNotConfirmed::class);
        $project->skipToday($this->user);
    }

    public function test_high_priority_skip_flow_in_the_ui_takes_two_confirmations(): void
    {
        $project = Project::factory()->create(['name' => 'Urgent']);
        $project->pinForDate(today());

        $test = Livewire::test(Daily::class)->call('skip', 'project', $project->id);
        $this->assertFalse($project->fresh()->isSkippedToday());
        $test->assertSet('confirm.step', 1)->assertSee('Skip a high priority item?');

        $test->call('advanceConfirm');
        $this->assertFalse($project->fresh()->isSkippedToday());
        $test->assertSet('confirm.step', 2)->assertSee('Are you really sure?');

        $test->call('advanceConfirm')->assertSet('confirm', null);
        $this->assertTrue($project->fresh()->isSkippedToday());
        $this->assertDatabaseHas('daily_skips', ['project_id' => $project->id, 'was_high' => true]);
    }

    public function test_cancelling_the_confirmation_skips_nothing(): void
    {
        $project = Project::factory()->create();
        $project->pinForDate(today());

        Livewire::test(Daily::class)->call('skip', 'project', $project->id)->call('cancelConfirm')->assertSet('confirm', null);

        $this->assertSame(0, DailySkip::count());
    }

    public function test_bulk_skip_skips_selected_but_never_high_priority_items(): void
    {
        $a = Project::factory()->create();
        $b = Project::factory()->create();
        $high = Project::factory()->create();
        $high->pinForDate(today());

        Livewire::test(Daily::class)
            ->set('selected', [$a->id, $b->id, $high->id])
            ->call('skipSelected')
            ->assertSet('selected', []);

        $this->assertTrue($a->fresh()->isSkippedToday());
        $this->assertTrue($b->fresh()->isSkippedToday());
        $this->assertFalse($high->fresh()->isSkippedToday());
    }

    public function test_high_tasks_are_on_the_priority_list_and_need_a_confirmed_skip(): void
    {
        $flagged = Task::factory()->create(['title' => 'Ship the thing', 'is_high' => true]);
        $normal = Task::factory()->create(['title' => 'Someday', 'is_high' => false, 'due_date' => null]);

        Livewire::test(Daily::class)->assertSee('Ship the thing')->assertDontSee('Someday');

        $this->expectException(HighPrioritySkipNotConfirmed::class);

        try {
            $normal->skipToday($this->user); // not high: fine
            $this->assertTrue($normal->fresh()->isSkippedToday());
        } finally {
            $flagged->skipToday($this->user);
        }
    }

    public function test_skips_are_append_only(): void
    {
        $skip = Project::factory()->create()->skipToday($this->user);

        $this->expectException(ImmutableRecord::class);
        $skip->delete();
    }

    public function test_skip_history_is_collapsed_by_default(): void
    {
        Project::factory()->create()->skipToday($this->user);

        $html = Livewire::test(Daily::class)->html();

        $this->assertStringContainsString('<details', $html);
        $this->assertStringNotContainsString('<details open', $html);
    }
}
