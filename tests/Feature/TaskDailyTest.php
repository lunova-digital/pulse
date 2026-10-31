<?php

namespace Tests\Feature;

use App\Enums\Recurrence;
use App\Livewire\Tasks;
use App\Models\DailySkip;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskDailyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_todays_list_is_open_tasks_due_today_or_earlier_or_undated(): void
    {
        $undated = Task::factory()->create();
        $due = Task::factory()->create(['due_date' => today()]);
        $overdue = Task::factory()->create(['due_date' => today()->subDays(3)]);
        Task::factory()->create(['due_date' => today()->addDays(2)]);
        $done = Task::factory()->create();
        $done->complete();

        $this->assertEqualsCanonicalizing([$undated->id, $due->id, $overdue->id], Task::untouchedToday()->pluck('id')->all());
    }

    public function test_skip_button_hides_a_task_for_today_logs_it_and_it_returns_tomorrow(): void
    {
        $task = Task::factory()->create(['title' => 'Quiet task']);

        Livewire::test(Tasks::class)->assertSee('1')->assertSee('not touched today')
            ->call('skip', $task->id)
            ->assertSee('Every task is touched today.');

        $this->assertDatabaseHas('daily_skips', ['task_id' => $task->id, 'user_id' => $this->user->id, 'was_high' => false]);
        $this->assertNull($task->fresh()->completed_at);
        Livewire::test(Tasks::class)->assertSee('Skip history')->assertSee('Quiet task');

        $this->travel(1)->day();
        Livewire::test(Tasks::class)->assertSee('Quiet task')->assertSee('not touched today');
    }

    public function test_skipping_a_recurring_task_moves_it_to_its_next_day(): void
    {
        $daily = Task::factory()->create(['recurrence' => Recurrence::Daily, 'due_date' => today()]);
        $weekly = Task::factory()->create(['recurrence' => Recurrence::Weekly, 'due_date' => today()->subDays(2)]);
        $monthly = Task::factory()->create(['recurrence' => Recurrence::Monthly, 'due_date' => today()]);

        foreach ([$daily, $weekly, $monthly] as $task) {
            $task->skipToday($this->user);
        }

        $this->assertTrue($daily->fresh()->due_date->isSameDay(today()->addDay()));
        $this->assertTrue($weekly->fresh()->due_date->isSameDay(today()->addDays(5)));
        $this->assertTrue($monthly->fresh()->due_date->isSameDay(today()->addMonthNoOverflow()));
        $this->assertSame(0, Task::untouchedToday()->count());

        // Skipping twice the same day does not push it further.
        $daily->fresh()->skipToday($this->user);
        $this->assertTrue($daily->fresh()->due_date->isSameDay(today()->addDay()));
        $this->assertSame(3, DailySkip::count());

        // Tomorrow the daily one is back on the list.
        $this->travel(1)->day();
        $this->assertSame([$daily->id], Task::untouchedToday()->pluck('id')->all());
    }

    public function test_completing_a_recurring_task_creates_the_next_one(): void
    {
        $task = Task::factory()->create(['title' => 'Backup', 'recurrence' => Recurrence::Daily, 'due_date' => today(), 'is_high' => true]);

        Livewire::test(Tasks::class)->call('toggle', $task->id);

        $this->assertTrue($task->fresh()->isDone());
        $next = Task::open()->where('title', 'Backup')->first();
        $this->assertNotNull($next);
        $this->assertTrue($next->due_date->isSameDay(today()->addDay()));
        $this->assertSame(Recurrence::Daily, $next->recurrence);
        $this->assertTrue($next->is_high);
    }

    public function test_bulk_skip_leaves_high_priority_tasks_and_a_recurring_task_can_be_added(): void
    {
        $a = Task::factory()->create();
        $b = Task::factory()->create();
        $high = Task::factory()->create(['is_high' => true]);

        Livewire::test(Tasks::class)->call('toggleSelecting')
            ->set('selected', [$a->id, $b->id, $high->id])->call('skipSelected')
            ->assertSet('selecting', false);

        $this->assertTrue(Task::untouchedToday()->whereKey($high->id)->exists());
        $this->assertSame(2, DailySkip::count());

        Livewire::test(Tasks::class)->set('title', 'Standup notes')->set('repeat', 'daily')->call('add');
        $this->assertSame(Recurrence::Daily, Task::where('title', 'Standup notes')->first()->recurrence);
    }

    public function test_members_can_only_skip_tasks_they_can_see(): void
    {
        $member = User::factory()->member()->create();
        $hidden = Task::factory()->create(['user_id' => $this->user->id]);
        $this->actingAs($member);

        $this->assertThrows(
            fn () => Livewire::test(Tasks::class)->call('skip', $hidden->id),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );
        $this->assertSame(0, DailySkip::count());
    }
}
