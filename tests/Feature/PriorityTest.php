<?php

namespace Tests\Feature;

use App\Exceptions\ImmutableRecord;
use App\Livewire\Projects\Index;
use App\Models\DailySkip;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriorityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_pinning_a_project_makes_it_todays_priority_and_it_carries_over(): void
    {
        $project = Project::factory()->create(['name' => 'Alpha']);

        Livewire::test(Index::class)->call('togglePin', $project->id)->assertSee('Priority today');
        $this->assertTrue($project->fresh()->is_pinned);

        // Nothing changes overnight: tomorrow it is still the priority.
        $this->travel(1)->day();
        $this->assertTrue($project->fresh()->is_pinned);
        Livewire::test(Index::class)->assertSee('Priority today')->assertSee('Alpha');

        Livewire::test(Index::class)->call('togglePin', $project->id);
        $this->assertFalse($project->fresh()->is_pinned);
    }

    public function test_priority_is_listed_first(): void
    {
        Project::factory()->create(['name' => 'Zed plain']);
        Project::factory()->create(['name' => 'Alpha pinned', 'is_pinned' => true]);

        Livewire::test(Index::class)->assertSeeInOrder(['Priority today', 'Alpha pinned', 'Everything else', 'Zed plain']);
    }

    public function test_projects_and_products_share_one_tab_with_a_type_filter_and_a_type_tag(): void
    {
        Project::factory()->create(['name' => 'A website']);
        Project::factory()->create(['name' => 'A saas', 'kind' => 'product']);

        Livewire::test(Index::class)->assertSee('A website')->assertSee('A saas')->assertSee('Product')->assertSee('Project');
        Livewire::test(Index::class)->set('type', 'product')->assertSee('A saas')->assertDontSee('A website');
        Livewire::test(Index::class)->set('type', 'project')->assertSee('A website')->assertDontSee('A saas');

        $this->get('/projects')->assertRedirect('/work');
        $this->get('/products')->assertRedirect('/work?type=product');
        $this->get('/work')->assertOk()->assertSee('Work');
    }

    public function test_new_items_can_be_a_project_or_a_product(): void
    {
        Livewire::test(Index::class)->set('type', 'product')->call('openNew')->assertSet('kind', 'product')
            ->set('name', 'My product')->call('create');
        Livewire::test(Index::class)->call('openNew')->assertSet('kind', 'project')
            ->set('name', 'My project')->set('kind', 'project')->call('create');

        $this->assertSame('product', Project::where('name', 'My product')->value('kind')->value);
        $this->assertSame('project', Project::where('name', 'My project')->value('kind')->value);
    }

    public function test_untouched_banner_counts_everything_even_when_filtered(): void
    {
        Project::factory()->create();
        Project::factory()->create(['kind' => 'product']);

        Livewire::test(Index::class)->set('type', 'product')->assertSee('not touched today')->assertSeeHtml('<b>2</b>');
    }

    public function test_bulk_skip_hides_projects_for_today_and_logs_them_silently(): void
    {
        $a = Project::factory()->create(['name' => 'Quiet A']);
        $b = Project::factory()->create(['name' => 'Quiet B']);
        $keep = Project::factory()->create(['name' => 'Keep me']);

        Livewire::test(Index::class)
            ->call('toggleSelecting')
            ->set('selected', [$a->id, $b->id])
            ->call('skipSelected')
            ->assertSet('selected', [])
            ->assertSet('selecting', false)
            ->assertSee('Keep me');

        $this->assertTrue($a->fresh()->isSkippedToday());
        $this->assertSame(0, $a->entries()->where('note', 'like', '%skip%')->count());
        $this->assertDatabaseHas('daily_skips', ['project_id' => $b->id, 'user_id' => $this->user->id, 'was_high' => false]);
        $this->assertFalse($keep->fresh()->isSkippedToday());

        // Hidden from the list today, shown in the collapsed history.
        Livewire::test(Index::class)->assertSee('Skip history')->assertSee('Today');

        // Back tomorrow.
        $this->travel(1)->day();
        Livewire::test(Index::class)->assertSee('Quiet A')->assertSee('Quiet B');
    }

    public function test_priority_projects_are_not_skipped_in_bulk(): void
    {
        $priority = Project::factory()->create(['is_pinned' => true]);
        $other = Project::factory()->create();

        Livewire::test(Index::class)
            ->set('selected', [$priority->id, $other->id])
            ->call('skipSelected');

        $this->assertFalse($priority->fresh()->isSkippedToday());
        $this->assertTrue($other->fresh()->isSkippedToday());
    }

    public function test_skips_are_append_only_and_unique_per_day(): void
    {
        $project = Project::factory()->create();

        $skip = $project->skipToday($this->user);
        $project->skipToday($this->user);

        $this->assertSame(1, DailySkip::count());

        $this->expectException(ImmutableRecord::class);
        $skip->delete();
    }

    public function test_archived_projects_cannot_be_pinned_or_skipped(): void
    {
        $project = Project::factory()->archived()->create();

        $this->assertThrows(fn () => $project->togglePriority(), \InvalidArgumentException::class);
        $this->assertThrows(fn () => $project->skipToday($this->user), \InvalidArgumentException::class);
    }

    public function test_the_daily_tab_is_gone_and_home_is_projects(): void
    {
        $this->get('/daily')->assertNotFound();
        $this->get('/')->assertRedirect(route('work.index'));
    }

    public function test_every_item_is_untouched_until_updated_or_skipped(): void
    {
        $updated = Project::factory()->create();
        $skipped = Project::factory()->create();
        $idle = Project::factory()->create();
        Project::factory()->archived()->create();

        $updated->postEntry(\App\Enums\EntryType::Note, 'Did work');
        $skipped->skipToday($this->user);

        $this->assertSame([$idle->id], Project::untouchedToday()->pluck('id')->all());

        $this->travel(1)->day();
        $this->assertSame(3, Project::untouchedToday()->count());
    }

    public function test_single_skip_button_skips_one_item_even_a_priority_one(): void
    {
        $plain = Project::factory()->create(['name' => 'Plain']);
        $priority = Project::factory()->create(['name' => 'Starred', 'is_pinned' => true]);

        Livewire::test(Index::class)
            ->assertSee('2')->assertSee('not touched today')
            ->call('skip', $plain->id)
            ->call('skip', $priority->id)
            ->assertSee('Everything is touched today.');

        $this->assertDatabaseHas('daily_skips', ['project_id' => $priority->id, 'was_high' => true]);
        $this->assertDatabaseHas('daily_skips', ['project_id' => $plain->id, 'was_high' => false]);
    }

    public function test_project_page_lists_its_own_skips(): void
    {
        $project = Project::factory()->create();
        $project->skipToday($this->user);

        $this->get("/projects/{$project->id}")->assertOk()->assertSee('Skip history');
        \Livewire\Livewire::test(\App\Livewire\Projects\Show::class, ['project' => $project])->call('skipToday');
        $this->assertSame(1, DailySkip::where('project_id', $project->id)->count());
    }
}
