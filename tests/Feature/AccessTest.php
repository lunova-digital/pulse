<?php

namespace Tests\Feature;

use App\Livewire\Projects\Index;
use App\Livewire\Projects\Show;
use App\Livewire\Tasks;
use App\Livewire\Team;
use App\Livewire\Today;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private Project $shared;

    private Project $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shared = Project::factory()->create(['name' => 'Shared one']);
        $this->hidden = Project::factory()->create(['name' => 'Hidden one']);
    }

    private function member(bool $clients = false): User
    {
        $user = User::factory()->member($clients)->create();
        $user->projects()->attach($this->shared);

        return $user;
    }

    public function test_owner_sees_everything(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)->assertSee('Shared one')->assertSee('Hidden one');
    }

    public function test_member_sees_only_assigned_items_everywhere(): void
    {
        $this->actingAs($this->member());
        Task::factory()->create(['title' => 'Secret task', 'project_id' => $this->hidden->id]);
        Task::factory()->create(['title' => 'Visible task', 'project_id' => $this->shared->id]);

        Livewire::test(Index::class)->assertSee('Shared one')->assertDontSee('Hidden one');
        Livewire::test(Today::class)->assertDontSee('Hidden one');
        Livewire::test(Tasks::class)->assertSee('Visible task')->assertDontSee('Secret task');

        $this->get("/projects/{$this->shared->id}")->assertOk();
        $this->get("/projects/{$this->hidden->id}")->assertForbidden();
    }

    public function test_member_cannot_act_on_unassigned_items(): void
    {
        $this->actingAs($this->member());

        $attempts = [
            fn () => Livewire::test(Index::class)->call('togglePin', $this->hidden->id),
            fn () => Livewire::test(Today::class)->call('done', Task::factory()->create(['project_id' => $this->hidden->id])->id),
        ];

        foreach ($attempts as $attempt) {
            $this->assertThrows($attempt, \Illuminate\Database\Eloquent\ModelNotFoundException::class);
        }

        $this->assertSame(0, \App\Models\DailySkip::count());
        $this->assertFalse($this->hidden->fresh()->is_pinned);
    }

    public function test_creator_of_a_project_is_assigned_to_it(): void
    {
        $member = User::factory()->member()->create();
        $this->actingAs($member);

        Livewire::test(Index::class)->set('type', 'product')
            ->call('openNew')->set('name', 'Mine')->call('create');

        $mine = Project::where('name', 'Mine')->first();
        $this->assertSame('product', $mine->kind->value);
        $this->assertTrue($member->projects()->whereKey($mine->id)->exists());
    }

    public function test_client_portal_user_is_read_only_and_sees_no_internal_entries(): void
    {
        $client = User::factory()->client()->create();
        $client->projects()->attach($this->shared);
        $this->shared->postEntry(\App\Enums\EntryType::Note, 'Public status', 'Shown to client');
        $this->shared->postEntry(\App\Enums\EntryType::Note, 'Budget worry', 'Team only', internal: true);

        $this->actingAs($client)->get('/')->assertRedirect(route('projects.show', $this->shared));

        $page = $this->get("/projects/{$this->shared->id}")->assertOk();
        $page->assertSee('Public status')->assertDontSee('Budget worry')->assertDontSee('Add update')->assertDontSee('Archive project');

        foreach (['/today', '/work', '/tasks', '/archived', '/clients', '/team'] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->get("/projects/{$this->hidden->id}")->assertForbidden();

        Livewire::test(Show::class, ['project' => $this->shared])->call('togglePin')->assertForbidden();
    }

    public function test_client_users_cannot_post_updates_through_the_panel(): void
    {
        $client = User::factory()->client()->create();
        $client->projects()->attach($this->shared);
        $this->actingAs($client);

        Livewire::test(\App\Livewire\AddUpdate::class)->call('show', $this->shared->id)->assertForbidden();
    }

    public function test_client_area_needs_the_clients_permission(): void
    {
        $this->actingAs($this->member(clients: false))->get('/clients')->assertForbidden();
        $this->actingAs($this->member(clients: true))->get('/clients')->assertOk();
    }

    public function test_only_the_owner_can_open_the_team_screen(): void
    {
        $this->actingAs($this->member(clients: true))->get('/team')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/team')->assertOk();
    }

    public function test_owner_creates_a_member_with_access_and_shows_the_password_once(): void
    {
        $this->actingAs(User::factory()->create());

        $test = Livewire::test(Team::class)
            ->call('openNew')
            ->set('name', 'Rafi')->set('email', 'rafi@team.test')->set('role', 'member')
            ->set('clients', true)->set('projectIds', [(string) $this->shared->id])
            ->call('save')
            ->assertHasNoErrors();

        $password = $test->get('credentials')['password'];
        $user = User::where('email', 'rafi@team.test')->first();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($password, $user->password));
        $this->assertSame('member', $user->role->value);
        $this->assertTrue($user->can_manage_clients);
        $this->assertSame([$this->shared->id], $user->projects()->pluck('projects.id')->all());

        $test->call('dismissCredentials')->assertSet('credentials', null);
    }

    public function test_client_portal_users_need_at_least_one_shared_project(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Team::class)->call('openNew')
            ->set('name', 'Acme')->set('email', 'acme@client.test')->set('role', 'client')
            ->call('save')->assertHasErrors('projectIds');
    }

    public function test_owner_account_cannot_be_edited_through_the_team_screen(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        Livewire::test(Team::class)->call('openEdit', $owner->id)->assertForbidden();
    }
}
