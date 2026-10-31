<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Livewire\Projects\Show;
use App\Livewire\Projects\TaskBoard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['name' => 'Board project']);
        $this->actingAs(User::factory()->create());
    }

    public function test_the_project_page_shows_a_three_column_board(): void
    {
        $this->get("/projects/{$this->project->id}")->assertOk()
            ->assertSee('To Do')->assertSee('In Progress')->assertSee('Complete')->assertSee('+ Add task');
    }

    public function test_tasks_are_created_on_the_board_in_todo_with_a_deadline(): void
    {
        Livewire::test(TaskBoard::class, ['project' => $this->project])
            ->set('title', '')->call('add')->assertHasErrors('title')
            ->set('title', 'Write copy')->set('due', '2030-02-03')->set('high', true)->call('add')->assertHasNoErrors()
            ->assertSee('Write copy');

        $task = $this->project->tasks()->first();
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame('2030-02-03', $task->due_date->format('Y-m-d'));
        $this->assertTrue($task->is_high);
    }

    public function test_dragging_between_columns_changes_status_and_done_logs_on_the_timeline(): void
    {
        $task = Task::factory()->create(['title' => 'Build login', 'project_id' => $this->project->id]);
        $board = Livewire::test(TaskBoard::class, ['project' => $this->project]);

        $board->call('move', $task->id, 'in_progress');
        $this->assertSame(TaskStatus::InProgress, $task->fresh()->status);
        $this->assertSame(0, $this->project->entries()->where('status_label', 'Task done')->count());

        $board->call('move', $task->id, 'done')->assertDispatched('entry-posted');
        $this->assertTrue($task->fresh()->isDone());
        $entry = $this->project->entries()->first();
        $this->assertSame('Task done', $entry->status_label);
        $this->assertStringContainsString('Build login', $entry->note);

        // Dragging it back out of Complete reopens it and logs nothing more.
        $board->call('move', $task->id, 'todo');
        $this->assertFalse($task->fresh()->isDone());
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);
        $this->assertSame(1, $this->project->entries()->where('status_label', 'Task done')->count());

        $board->call('move', $task->id, 'bogus')->assertStatus(422);
    }

    public function test_deadline_and_priority_can_be_changed_on_a_card(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        Livewire::test(TaskBoard::class, ['project' => $this->project])
            ->call('setDue', $task->id, '2031-05-06')->call('toggleHigh', $task->id);

        $this->assertSame('2031-05-06', $task->fresh()->due_date->format('Y-m-d'));
        $this->assertTrue($task->fresh()->is_high);
    }

    public function test_the_board_only_touches_its_own_projects_tasks(): void
    {
        $other = Task::factory()->create(['project_id' => Project::factory()->create()->id]);

        $this->assertThrows(
            fn () => Livewire::test(TaskBoard::class, ['project' => $this->project])->call('move', $other->id, 'done'),
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        );
    }

    public function test_members_without_access_and_client_users_cannot_use_the_board(): void
    {
        $this->actingAs(User::factory()->member()->create());
        Livewire::test(TaskBoard::class, ['project' => $this->project])->assertForbidden();

        $client = User::factory()->client()->create();
        $client->projects()->attach($this->project);
        $this->actingAs($client);
        Livewire::test(TaskBoard::class, ['project' => $this->project])->assertForbidden();
        $this->get("/projects/{$this->project->id}")->assertOk()->assertDontSee('+ Add task');
    }

    public function test_archived_projects_have_no_board(): void
    {
        $archived = Project::factory()->archived()->create();

        $this->get("/projects/{$archived->id}")->assertOk()->assertDontSee('+ Add task');
    }

    public function test_existing_tasks_get_a_status_from_whether_they_were_completed(): void
    {
        $open = Task::factory()->create(['project_id' => $this->project->id]);
        $done = Task::factory()->create(['project_id' => $this->project->id]);
        $done->complete();

        $this->assertSame(TaskStatus::Todo, $open->fresh()->status);
        $this->assertSame(TaskStatus::Done, $done->fresh()->status);
    }

    public function test_the_flat_task_list_still_works_and_shows_status(): void
    {
        $task = Task::factory()->create(['title' => 'Loose task', 'project_id' => $this->project->id, 'status' => 'in_progress']);

        $this->get('/tasks')->assertOk()->assertSee('Loose task')->assertSee('In Progress');
    }
}
