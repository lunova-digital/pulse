<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\ReminderLog;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config(['services.telegram.bot_token' => 'TEST:token', 'services.telegram.chat_id' => '-100123']);
    }

    public function test_nothing_is_sent_until_the_bot_is_configured(): void
    {
        config(['services.telegram.bot_token' => null, 'services.telegram.chat_id' => null, 'services.telegram.owner_chat_id' => null]);
        Http::fake();
        Client::factory()->followUp(today())->create();

        $this->artisan('pulse:telegram-digest')->assertSuccessful();
        $this->artisan('pulse:telegram-plan')->assertSuccessful();
        $this->artisan('pulse:telegram-due')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_digest_lists_follow_ups_overdue_clients_and_priorities(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        Client::factory()->followUp(today()->setTime(15, 0), true)->create(['name' => 'Today & Co']);
        Client::factory()->followUp(today()->subDays(2))->create(['name' => 'Late Ltd']);
        Client::factory()->followUp(today()->addDay())->create(['name' => 'Tomorrow Inc']);
        Client::factory()->followUp(today())->inStage('Won')->create(['name' => 'Already Won']);
        $open = Project::factory()->create(['name' => 'Needs update']);
        $open->togglePriority();
        $skipped = Project::factory()->create(['name' => 'Skipped one']);
        $skipped->togglePriority();
        $skipped->skipToday(\App\Models\User::factory()->create());

        $this->artisan('pulse:telegram-digest')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $text = $request['text'];

            return str_contains($request->url(), '/botTEST:token/sendMessage')
                && $request['chat_id'] === '-100123'
                && $request['parse_mode'] === 'HTML'
                && str_contains($text, 'Today &amp; Co') && str_contains($text, '3:00 PM')
                && str_contains($text, 'Late Ltd') && str_contains($text, '2d overdue')
                && str_contains($text, 'Needs update')
                && ! str_contains($text, 'Tomorrow Inc') && ! str_contains($text, 'Already Won')
                && ! str_contains($text, 'Skipped one');
        });
    }

    public function test_digest_is_silent_when_there_is_nothing_to_report(): void
    {
        $this->configure();
        Http::fake();

        $this->artisan('pulse:telegram-digest')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_a_failed_send_makes_the_digest_command_fail(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 400)]);
        Client::factory()->followUp(today())->create();

        $this->artisan('pulse:telegram-digest')->assertFailed();
    }

    public function test_timed_follow_ups_ping_once_when_their_time_arrives(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $due = Client::factory()->followUp(now()->subMinutes(5), true)->create(['name' => 'Due now']);
        Client::factory()->followUp(now()->addHour(), true)->create(['name' => 'Not yet']);
        Client::factory()->followUp(now()->subMinutes(5), false)->create(['name' => 'No time set']);
        Client::factory()->followUp(now()->subDay(), true)->create(['name' => 'Ancient']);

        $this->artisan('pulse:telegram-due')->assertSuccessful();
        $this->artisan('pulse:telegram-due')->assertSuccessful(); // next scheduler run

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Due now') && ! str_contains($r['text'], 'Not yet'));
        $this->assertSame(1, ReminderLog::where('client_id', $due->id)->count());
    }

    public function test_a_rescheduled_follow_up_pings_again(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $client = Client::factory()->followUp(now()->subMinutes(10), true)->create();
        $this->artisan('pulse:telegram-due');

        $client->update(['next_followup_at' => now()->subMinutes(2)]);
        $this->artisan('pulse:telegram-due');

        Http::assertSentCount(2);
    }

    public function test_a_failed_ping_is_retried_on_the_next_run(): void
    {
        $this->configure();
        Http::fakeSequence('api.telegram.org/*')->push(['ok' => false], 500)->push(['ok' => true]);
        Client::factory()->followUp(now()->subMinutes(5), true)->create();

        $this->artisan('pulse:telegram-due');
        $this->assertSame(0, ReminderLog::count());

        $this->artisan('pulse:telegram-due');
        $this->assertSame(1, ReminderLog::count());
    }

    public function test_plan_reminder_asks_for_tomorrows_priority_and_lists_what_carries_over(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Project::factory()->create(['name' => 'Carries over', 'is_pinned' => true]);
        Project::factory()->create(['name' => 'Not pinned']);

        $this->artisan('pulse:telegram-plan')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'plan tomorrow')
            && str_contains($r['text'], 'Carries over') && ! str_contains($r['text'], 'Not pinned'));
    }

    public function test_plan_reminder_still_sends_when_nothing_is_pinned(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->artisan('pulse:telegram-plan')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Nothing is pinned'));
    }

    public function test_plan_reminder_goes_to_the_personal_chat_and_not_the_group_when_set(): void
    {
        $this->configure();
        config(['services.telegram.owner_chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->artisan('pulse:telegram-plan')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r['chat_id'] === '555' && str_contains($r['text'], 'plan tomorrow'));
    }

    public function test_other_reminders_still_go_to_the_group(): void
    {
        $this->configure();
        config(['services.telegram.owner_chat_id' => '555']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Project::factory()->create();

        $this->artisan('pulse:telegram-untouched')->assertSuccessful();

        Http::assertSent(fn (Request $r) => $r['chat_id'] === '-100123');
    }

    public function test_plan_reminder_is_scheduled_at_ten_pm(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'pulse:telegram-plan'));

        $this->assertNotNull($event);
        $this->assertSame('0 22 * * *', $event->expression);
    }

    public function test_untouched_reminder_lists_items_and_goes_quiet_when_cleared(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $a = Project::factory()->create(['name' => 'Idle A']);
        $b = Project::factory()->create(['name' => 'Idle B']);
        $c = Project::factory()->create(['name' => 'Updated C']);
        $c->postEntry(\App\Enums\EntryType::Note, 'Done');

        $this->artisan('pulse:telegram-untouched')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r['text'], '2 not touched today')
            && str_contains($r['text'], 'Idle A') && str_contains($r['text'], 'Idle B') && ! str_contains($r['text'], 'Updated C'));

        $user = \App\Models\User::factory()->create();
        $a->skipToday($user);
        $b->skipToday($user);
        $this->artisan('pulse:telegram-untouched')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_untouched_reminder_caps_a_long_list(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Project::factory()->count(30)->create();

        $this->artisan('pulse:telegram-untouched')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r['text'], '30 not touched today') && str_contains($r['text'], 'and 5 more'));
    }

    public function test_untouched_reminder_runs_only_at_the_configured_hours(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'pulse:telegram-untouched'));
        $this->assertNotNull($event);

        foreach ([11 => true, 14 => true, 20 => true, 9 => false, 22 => false] as $hour => $due) {
            $this->travelTo(now(config('app.timezone'))->setTime($hour, 0));
            $this->assertSame($due, $event->filtersPass(app()), "hour {$hour}");
        }
    }

    public function test_both_commands_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');

        $this->assertStringContainsString('pulse:telegram-digest', $commands);
        $this->assertStringContainsString('pulse:telegram-due', $commands);
    }
}
