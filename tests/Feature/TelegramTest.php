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
        Http::fake();
        Client::factory()->followUp(today())->create();

        $this->artisan('pulse:telegram-digest')->assertSuccessful();
        $this->artisan('pulse:telegram-due')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_digest_lists_follow_ups_overdue_clients_and_unchecked_priorities(): void
    {
        $this->configure();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        Client::factory()->followUp(today()->setTime(15, 0), true)->create(['name' => 'Today & Co']);
        Client::factory()->followUp(today()->subDays(2))->create(['name' => 'Late Ltd']);
        Client::factory()->followUp(today()->addDay())->create(['name' => 'Tomorrow Inc']);
        Client::factory()->followUp(today())->inStage('Won')->create(['name' => 'Already Won']);
        $open = Project::factory()->create(['name' => 'Needs update']);
        $open->pinForDate(today());
        $done = Project::factory()->create(['name' => 'Posted already']);
        $done->pinForDate(today());
        $done->postEntry(\App\Enums\EntryType::Note, 'Hi', 'Done');

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
                && ! str_contains($text, 'Posted already');
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

    public function test_both_commands_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');

        $this->assertStringContainsString('pulse:telegram-digest', $commands);
        $this->assertStringContainsString('pulse:telegram-due', $commands);
    }
}
