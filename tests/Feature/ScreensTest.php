<?php

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\WhoseMove;
use App\Livewire\AddUpdate;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\Show;
use App\Livewire\Tasks;
use App\Livewire\Today;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\SampleProjectsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_every_screen_renders_with_sample_data(): void
    {
        $this->seed(SampleProjectsSeeder::class);
        $active = Project::active()->first();
        $archived = Project::archived()->first();

        foreach (['/today', '/work', '/archived', '/tasks', "/projects/{$active->id}", "/projects/{$archived->id}"] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/today')->assertSee('Due today')->assertSee('In progress');
        $this->get('/work')->assertSee('AshesMart')->assertDontSee('Dhaka Dental Care');
        $this->get('/archived')->assertSee('Dhaka Dental Care')->assertSee('Client paused budget');
    }

    public function test_there_are_no_delete_routes(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_contains($route->getActionName(), 'RedirectController')) {
                continue; // Route::redirect answers every verb
            }

            $this->assertNotContains('DELETE', $route->methods(), $route->uri());
        }
    }

    public function test_today_shows_overdue_due_today_and_in_progress_tasks_only(): void
    {
        $p = Project::factory()->create(['name' => 'Acme site']);
        Task::factory()->create(['title' => 'Late one', 'project_id' => $p->id, 'due_date' => today()->subDay()]);
        Task::factory()->create(['title' => 'Due now', 'project_id' => $p->id, 'due_date' => today()]);
        Task::factory()->create(['title' => 'Underway', 'project_id' => $p->id, 'status' => 'in_progress', 'due_date' => today()->addWeek()]);
        Task::factory()->create(['title' => 'Someday', 'project_id' => $p->id, 'due_date' => today()->addWeek()]);
        Task::factory()->create(['title' => 'Backlog', 'project_id' => $p->id]);
        $done = Task::factory()->create(['title' => 'Finished', 'project_id' => $p->id, 'due_date' => today()]);
        $done->complete();
        $archived = Project::factory()->archived()->create();
        Task::factory()->create(['title' => 'Archived work', 'project_id' => $archived->id, 'due_date' => today()]);

        Livewire::test(Today::class)
            ->assertSee('Late one')->assertSee('Due now')->assertSee('Underway')->assertSee('Acme site')
            ->assertDontSee('Someday')->assertDontSee('Backlog')->assertDontSee('Finished')->assertDontSee('Archived work')
            ->assertDontSee('Needs our action');
    }

    public function test_today_done_and_start_move_tasks_and_log_completion(): void
    {
        $p = Project::factory()->create();
        $task = Task::factory()->create(['title' => 'Ship it', 'project_id' => $p->id, 'due_date' => today()]);

        Livewire::test(Today::class)->call('start', $task->id);
        $this->assertSame(\App\Enums\TaskStatus::InProgress, $task->fresh()->status);

        Livewire::test(Today::class)->call('done', $task->id)->assertDispatched('toast');
        $this->assertTrue($task->fresh()->isDone());
        $this->assertSame(\App\Enums\TaskStatus::Done, $task->fresh()->status);
        $this->assertStringContainsString('Ship it', $p->entries()->first()->note);
    }

    public function test_nudge_opens_add_update_prefilled(): void
    {
        $project = Project::factory()->waitingOn(WhoseMove::Client)->create();

        Livewire::test(AddUpdate::class)
            ->dispatch('open-update', projectId: $project->id, preset: ['type' => 'note', 'label' => 'Nudged client', 'note' => ''])
            ->assertSet('form.label', 'Nudged client')
            ->assertSet('form.type', 'note')
            ->assertSet('fixed', true)
            ->call('post');

        $this->assertSame('Nudged client', $project->entries()->first()->status_label);
        $this->assertSame(EntryType::Note, $project->entries()->first()->type);
    }

    public function test_add_update_panel_with_project_picker(): void
    {
        $a = Project::factory()->waitingOn(WhoseMove::Us)->create();
        $b = Project::factory()->waitingOn(WhoseMove::None)->create();

        Livewire::test(AddUpdate::class)
            ->dispatch('open-update')
            ->assertSet('fixed', false)
            ->call('post')
            ->assertHasErrors('form.projectId')
            ->set('form.projectId', $b->id)
            ->assertSet('form.move', 'none')
            ->set('form.type', 'call')
            ->set('form.note', 'Spoke to them')
            ->set('form.move', 'client')
            ->call('post')
            ->assertHasNoErrors()
            ->assertSet('open', false)
            ->assertDispatched('entry-posted');

        $b->refresh();
        $this->assertSame(WhoseMove::Client, $b->whose_move);
        $this->assertSame(EntryType::Call, $b->entries()->first()->type);
        $this->assertSame('Call', $b->entries()->first()->status_label);
        $this->assertSame(0, $a->entries()->count());
    }

    public function test_add_update_requires_label_or_note(): void
    {
        $p = Project::factory()->create();

        Livewire::test(AddUpdate::class)
            ->dispatch('open-update', projectId: $p->id)
            ->call('post')
            ->assertHasErrors('form.label');

        $this->assertSame(0, $p->entries()->count());
    }

    public function test_projects_search_filter_and_pinning_order(): void
    {
        $old = Project::factory()->create(['name' => 'Alpha', 'whose_move' => WhoseMove::Us, 'last_activity_at' => now()->subDays(2)]);
        $new = Project::factory()->create(['name' => 'Beta', 'whose_move' => WhoseMove::Client, 'client_name' => 'Zed Corp']);
        $pinned = Project::factory()->create(['name' => 'Gamma', 'whose_move' => WhoseMove::Us, 'is_pinned' => true, 'last_activity_at' => now()->subDays(9)]);

        Livewire::test(Index::class)->assertSeeInOrder(['Gamma', 'Beta', 'Alpha'])
            ->set('filter', 'us')->assertDontSee('Beta')
            ->set('filter', 'all')->set('search', 'zed')->assertSee('Beta')->assertDontSee('Alpha');
    }

    public function test_create_project_modal(): void
    {
        Livewire::test(Index::class)
            ->call('openNew')
            ->call('create')
            ->assertHasErrors('name')
            ->set('name', 'Clinic site')
            ->set('client', 'Dr. Who')
            ->set('template', 'simple')
            ->set('move', 'client')
            ->set('meeting', '2030-01-02T10:30')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect();

        $p = Project::firstWhere('name', 'Clinic site');
        $this->assertSame(['Kickoff', 'Build', 'Delivery'], $p->milestones->pluck('title')->all());
        $this->assertSame(WhoseMove::Client, $p->whose_move);
        $this->assertSame('2030-01-02 10:30', $p->next_meeting_at->format('Y-m-d H:i'));
        $this->assertSame(1, $p->entries()->count());
    }

    public function test_detail_whose_move_pin_meeting_and_update(): void
    {
        $p = Project::factory()->create(['whose_move' => WhoseMove::Us]);

        $c = Livewire::test(Show::class, ['project' => $p])
            ->call('changeMove', 'client')
            ->assertSet('form.move', 'client')
            ->call('togglePin')
            ->set('meeting', '2030-05-06T14:00')
            ->set('form.note', 'Sent v1')
            ->call('postUpdate')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertSame(WhoseMove::Client, $p->whose_move);
        $this->assertTrue($p->is_pinned);
        $this->assertSame('2030-05-06 14:00', $p->next_meeting_at->format('Y-m-d H:i'));
        $this->assertSame(3, $p->entries()->count()); // move change, meeting scheduled, update
        $this->assertStringContainsString('Whose move changed from Waiting on us to Waiting on client.', $p->entries()->get()->last()->note);

        $c->set('filter', 'call')->assertDontSee('Sent v1');
    }

    public function test_archive_requires_a_reason_then_redirects(): void
    {
        $p = Project::factory()->create();

        Livewire::test(Show::class, ['project' => $p])
            ->call('openArchive')
            ->set('reason', '   ')
            ->call('confirmReason')
            ->assertHasErrors('reason');

        $this->assertFalse($p->fresh()->isArchived());

        Livewire::test(Show::class, ['project' => $p])
            ->call('openArchive')
            ->set('reason', 'Finished')
            ->call('confirmReason')
            ->assertRedirect(route('archived'));

        $this->assertTrue($p->fresh()->isArchived());
    }

    public function test_archived_project_is_read_only_and_can_be_reopened(): void
    {
        $p = Project::factory()->archived()->create();
        $p->entries()->create(['type' => EntryType::Archived, 'status_label' => 'Archived', 'note' => 'Done', 'created_at' => now()]);

        $c = Livewire::test(Show::class, ['project' => $p])
            ->assertSee('Reopen project')
            ->assertSee('Done')
            ->assertDontSee('Add update')
            ->assertDontSee('Edit roadmap');

        Livewire::test(Show::class, ['project' => $p])->set('form.note', 'sneaky')->call('postUpdate')->assertForbidden();
        Livewire::test(Show::class, ['project' => $p])->call('togglePin')->assertForbidden();

        $c->call('openReopen')->set('reason', '')->call('confirmReason')->assertHasErrors('reason');
        $c->set('reason', 'Client came back')->call('confirmReason')->assertHasNoErrors();

        $p->refresh();
        $this->assertFalse($p->isArchived());
        $this->assertSame(EntryType::Reopened, $p->entries()->first()->type);
    }

    public function test_roadmap_editor(): void
    {
        $p = Project::factory()->create();

        Livewire::test(Show::class, ['project' => $p])
            ->call('openRoadmap')
            ->assertSet('rm.cur', 0)
            ->call('moveStep', 0, 1)
            ->assertSet('rm.cur', 1)
            ->call('removeStep', 5)
            ->call('addStep')
            ->call('setCurrent', 2)
            ->call('saveRoadmap')
            ->assertSet('modal', null);

        $p->refresh();
        $titles = $p->milestones->pluck('title')->all();
        $this->assertSame(['Design', 'SRS', 'Development', 'Testing', 'Delivery', 'New milestone'], $titles);
        $this->assertSame('Roadmap: Development', $p->entries()->first()->status_label);
    }

    public function test_tasks_add_complete_and_log_on_project(): void
    {
        $p = Project::factory()->create();

        $c = Livewire::test(Tasks::class)
            ->set('title', '')->call('add')->assertHasErrors('title')
            ->set('title', 'Send invoice')->set('projectId', $p->id)->set('due', '2030-01-01')
            ->call('add')->assertHasNoErrors()
            ->assertSee('Send invoice')->assertSee('Open · 1');

        $task = Task::firstWhere('title', 'Send invoice');
        $this->assertSame($p->id, $task->project_id);

        $c->call('toggle', $task->id)->assertSee('Done · 1')->assertDispatched('toast');
        $this->assertTrue($task->fresh()->isDone());
        $this->assertStringContainsString('Send invoice', $p->entries()->first()->note);

        $c->call('toggle', $task->id);
        $this->assertFalse($task->fresh()->isDone());
        $this->assertSame(1, $p->entries()->count()); // reopening a task logs nothing
    }

    public function test_task_without_project_has_null_project_id(): void
    {
        Livewire::test(Tasks::class)->set('title', 'Loose end')->set('projectId', null)->call('add');

        $this->assertNull(Task::firstWhere('title', 'Loose end')->project_id);
    }
}
