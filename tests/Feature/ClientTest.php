<?php

namespace Tests\Feature;

use App\Enums\ClientEntryType;
use App\Exceptions\ClientCannotBeDeleted;
use App\Exceptions\ImmutableRecord;
use App\Livewire\Clients\Index;
use App\Livewire\Clients\Show;
use App\Models\Client;
use App\Models\PipelineStage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_default_stages_are_seeded_by_the_migration(): void
    {
        $this->assertSame(
            ['Lead', 'Contacted', 'Meeting', 'Proposal', 'Negotiation', 'Won', 'Lost'],
            PipelineStage::visible()->pluck('name')->all(),
        );
    }

    public function test_clients_are_never_deleted_and_their_history_is_append_only(): void
    {
        $client = Client::start(['name' => 'Acme']);

        $this->assertThrows(fn () => $client->delete(), ClientCannotBeDeleted::class);
        $this->assertThrows(fn () => $client->entries()->first()->update(['note' => 'x']), ImmutableRecord::class);
        $this->assertThrows(fn () => $client->entries()->first()->delete(), ImmutableRecord::class);
    }

    public function test_a_new_client_starts_in_the_first_stage_with_an_opening_entry(): void
    {
        $client = Client::start(['name' => 'Acme'], $this->user);

        $this->assertSame('Lead', $client->stage->name);
        $this->assertSame(1, $client->entries()->count());
        $this->assertSame($this->user->id, $client->owner_user_id);
    }

    public function test_logging_an_update_requires_a_next_follow_up_or_an_explicit_none(): void
    {
        $client = Client::start(['name' => 'Acme']);

        $this->assertThrows(
            fn () => $client->logUpdate(ClientEntryType::Call, 'Spoke', null),
            InvalidArgumentException::class,
        );

        $client->logUpdate(ClientEntryType::Call, 'Spoke', now()->addDays(2)->startOfDay());
        $this->assertTrue($client->fresh()->next_followup_at->isSameDay(now()->addDays(2)));

        $client->logUpdate(ClientEntryType::Note, 'Closed loop', null, noFollowUp: true);
        $this->assertNull($client->fresh()->next_followup_at);
    }

    public function test_logging_through_the_ui_enforces_the_follow_up_rule(): void
    {
        $client = Client::start(['name' => 'Acme']);
        $date = now()->addDays(3)->format('Y-m-d');

        Livewire::test(Show::class, ['client' => $client])
            ->set('note', 'Talked pricing')->call('logUpdate')->assertHasErrors('nextDate');

        Livewire::test(Show::class, ['client' => $client])
            ->set('note', 'Talked pricing')->set('nextDate', $date)->set('nextTime', '15:30')->call('logUpdate')->assertHasNoErrors();

        $fresh = $client->fresh();
        $this->assertTrue($fresh->followup_has_time);
        $this->assertSame($date.' 15:30', $fresh->next_followup_at->format('Y-m-d H:i'));

        Livewire::test(Show::class, ['client' => $client])
            ->set('note', 'All done')->set('noFollowUp', true)->call('logUpdate')->assertHasNoErrors();
        $this->assertNull($client->fresh()->next_followup_at);
    }

    public function test_stage_changes_are_logged(): void
    {
        $client = Client::start(['name' => 'Acme']);
        $proposal = PipelineStage::where('name', 'Proposal')->first();

        Livewire::test(Show::class, ['client' => $client])->call('changeStage', $proposal->id);

        $this->assertSame($proposal->id, $client->fresh()->stage_id);
        $entry = $client->entries()->first();
        $this->assertSame(ClientEntryType::StageChange, $entry->type);
        $this->assertSame($proposal->id, $entry->to_stage_id);
    }

    public function test_starting_a_project_converts_the_client_to_won_and_links_the_project(): void
    {
        $client = Client::start(['name' => 'Acme Ltd']);

        Livewire::test(Show::class, ['client' => $client])
            ->call('openProject')
            ->assertSet('projectName', 'Acme Ltd project')
            ->call('startProject')
            ->assertRedirect();

        $project = Project::first();
        $this->assertSame($client->id, $project->client_id);
        $this->assertSame('Acme Ltd', $project->client_name);
        $this->assertSame('Won', $client->fresh()->stage->name);
        $this->assertTrue($client->entries()->first()->note === "Project started: {$project->name}.");
        $this->assertCount(1, $client->projects);
    }

    public function test_due_and_overdue_scopes_ignore_won_and_lost_clients(): void
    {
        Client::factory()->followUp(today())->create(['name' => 'Today']);
        Client::factory()->followUp(today()->subDays(3))->create(['name' => 'Late']);
        Client::factory()->followUp(today()->addDay())->create(['name' => 'Later']);
        Client::factory()->followUp(today())->inStage('Won')->create(['name' => 'Closed']);
        Client::factory()->followUp(today()->subDay())->inStage('Lost')->create(['name' => 'Dead']);

        $this->assertEqualsCanonicalizing(['Today', 'Late'], Client::due()->pluck('name')->all());
        $this->assertSame(['Late'], Client::overdue()->pluck('name')->all());

        Livewire::test(Index::class)->set('filter', 'due')->assertSee('Late')->assertSee('Today')->assertDontSee('Later')->assertDontSee('Closed');
    }

    public function test_funnel_screen_and_new_client_form(): void
    {
        $this->get('/clients')->assertOk();

        Livewire::test(Index::class)->call('openNew')->set('name', 'Fresh Co')->call('create')->assertRedirect();

        $this->assertSame('Lead', Client::where('name', 'Fresh Co')->first()->stage->name);
        $this->get('/clients/'.Client::first()->id)->assertOk()->assertSee('Fresh Co');
    }

    public function test_stages_can_be_renamed_reordered_added_and_hidden_but_not_deleted(): void
    {
        $lead = PipelineStage::where('name', 'Lead')->first();

        $test = Livewire::test(Index::class)->call('openStages')
            ->set("stageNames.{$lead->id}", 'New lead')->call('renameStage', $lead->id)
            ->set('newStage', 'Referral')->call('addStage');

        $this->assertSame('New lead', $lead->fresh()->name);
        $this->assertTrue(PipelineStage::where('name', 'Referral')->exists());

        $contacted = PipelineStage::where('name', 'Contacted')->first();
        $test->call('moveStage', $contacted->id, -1);
        $this->assertSame('Contacted', PipelineStage::visible()->first()->name);

        $this->assertThrows(fn () => $lead->delete(), \LogicException::class);

        // A stage in use cannot be hidden; an empty one can.
        Client::start(['name' => 'Sits in lead', 'stage_id' => $lead->id]);
        $test->call('toggleHidden', $lead->id)->assertHasErrors('stages');
        $this->assertFalse($lead->fresh()->is_hidden);

        $referral = PipelineStage::where('name', 'Referral')->first();
        $test->call('toggleHidden', $referral->id);
        $this->assertTrue($referral->fresh()->is_hidden);
    }

    public function test_the_last_won_stage_cannot_be_unmarked_or_hidden(): void
    {
        $won = PipelineStage::where('name', 'Won')->first();

        Livewire::test(Index::class)->call('openStages')->call('toggleFlag', $won->id, 'won')->assertHasErrors('stages');
        $this->assertTrue($won->fresh()->is_won);

        $this->assertThrows(fn () => $won->hide(), \LogicException::class);
    }
}
