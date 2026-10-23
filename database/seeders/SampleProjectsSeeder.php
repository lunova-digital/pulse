<?php

namespace Database\Seeders;

use App\Enums\EntryType;
use App\Enums\WhoseMove;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * The sample data from the design. Dates are written relative to the design's
 * "today" (2026-10-23) and shifted so the data always looks freshly used.
 */
class SampleProjectsSeeder extends Seeder
{
    private const ANCHOR = '2026-10-23';

    private const STD = ['SRS', 'Design', 'Development', 'Testing', 'Delivery', 'Maintenance'];

    private int $shift;

    public function run(): void
    {
        $this->shift = (int) Carbon::parse(self::ANCHOR)->startOfDay()->diffInDays(today(), false);

        foreach ($this->projects() as $data) {
            $this->seedProject($data);
        }

        foreach ($this->tasks() as [$title, $slug, $due, $done]) {
            Task::create([
                'title' => $title,
                'project_id' => $slug ? Project::where('name', $this->names[$slug])->value('id') : null,
                'due_date' => $due ? $this->at($due)->toDateString() : null,
                'completed_at' => $done ? $this->at('2026-10-13 12:00') : null,
            ]);
        }
    }

    private array $names = [];

    private function at(string $stamp): Carbon
    {
        return Carbon::parse($stamp)->addDays($this->shift);
    }

    private function seedProject(array $d): void
    {
        $this->names[$d['slug']] = $d['name'];

        $entries = collect($d['entries'])->map(fn ($e) => [
            'type' => $e[0], 'at' => $this->at($e[1]), 'label' => $e[2], 'note' => $e[3], 'move' => $e[4],
        ]);

        $project = Project::create([
            'name' => $d['name'],
            'client_name' => $d['client'],
            'description' => $d['desc'],
            'whose_move' => $d['move'],
            'whose_move_since' => $this->at($d['since']),
            'next_meeting_at' => isset($d['meeting']) ? $this->at($d['meeting']) : null,
            'is_pinned' => $d['pinned'] ?? false,
            'archived_at' => isset($d['archived_at']) ? $this->at($d['archived_at']) : null,
            'last_activity_at' => $entries->max('at'),
            'created_at' => $entries->min('at'),
        ]);

        $project->replaceRoadmap($d['roadmap'] ?? self::STD, $d['cur']);

        foreach ($entries as $e) {
            $project->entries()->create([
                'type' => $e['type'],
                'status_label' => $e['label'],
                'note' => $e['note'],
                'whose_move' => $e['move'],
                'created_at' => $e['at'],
            ]);
        }
    }

    private function projects(): array
    {
        $us = WhoseMove::Us;
        $client = WhoseMove::Client;
        $none = WhoseMove::None;
        $call = EntryType::Call;
        $meeting = EntryType::Meeting;
        $dev = EntryType::Development;
        $delivery = EntryType::Delivery;
        $feedback = EntryType::ClientFeedback;
        $note = EntryType::Note;

        return [
            ['slug' => 'ashes', 'name' => 'AshesMart', 'client' => 'Ashes Group', 'desc' => 'E-commerce store with a referral rewards program.', 'move' => $us, 'since' => '2026-10-21 10:05', 'pinned' => true, 'meeting' => '2026-10-28 16:00', 'cur' => 2, 'entries' => [
                [$feedback, '2026-10-21 10:05', 'Referral bug reported', 'Referral credit is not applied when the invited customer pays with bKash.', $us],
                [$dev, '2026-10-19 15:30', 'Referral module on staging', 'Referral links, tracking and wallet credit merged to staging for review.', $client],
                [$meeting, '2026-10-16 11:00', 'Sprint review', 'Walked through checkout and product filters. Approved. Leaderboard moved to phase 2.', $us],
                [$dev, '2026-10-12 17:20', 'Checkout ready', 'Checkout with bKash and cash on delivery working on staging.', $us],
                [$call, '2026-10-08 12:00', 'Gateway credentials', 'Their accountant will send SSLCommerz credentials this week.', $us],
                [$note, '2026-10-03 10:30', 'Design approved', 'Final Figma screens signed off by the client.', $us],
            ]],
            ['slug' => 'royal', 'name' => 'Royal Housing BD', 'client' => 'Royal Housing Ltd.', 'desc' => 'Real-estate website redesign with a new property search.', 'move' => $client, 'since' => '2026-10-17 14:00', 'meeting' => '2026-10-20 11:00', 'cur' => 1, 'entries' => [
                [$delivery, '2026-10-17 14:00', 'Homepage v2 sent', 'Sent homepage and listing page v2 with the new search bar.', $client],
                [$dev, '2026-10-14 16:00', 'Listing cards', 'Property cards now show price per sqft and handover date.', $us],
                [$feedback, '2026-10-10 12:30', 'Revisions requested', 'Wants larger project photos and a Bangla toggle in the header.', $us],
                [$meeting, '2026-10-06 11:00', 'Kickoff', 'Collected brand assets. 14 ongoing projects to feature.', $us],
                [$call, '2026-10-04 09:30', 'Scope confirmed', 'Redesign only. No CMS migration in this phase.', $client],
            ]],
            ['slug' => 'nfc', 'name' => 'NFC Tap Menu', 'client' => 'Kacchi House, Dhanmondi', 'desc' => 'NFC table cards that open the restaurant menu on any phone.', 'move' => $client, 'since' => '2026-10-20 18:00', 'meeting' => '2026-10-25 15:00', 'cur' => 3, 'entries' => [
                [$delivery, '2026-10-20 18:00', 'Test cards delivered', '20 programmed NFC cards handed over for table testing.', $client],
                [$dev, '2026-10-18 13:00', 'Live menu sync', 'Menu editor now updates the public menu instantly.', $us],
                [$call, '2026-10-15 20:00', 'Card printing', 'Printer confirmed matte finish with a 3 day turnaround.', $us],
                [$feedback, '2026-10-11 15:00', 'Final menu received', '46 items with prices and photos.', $us],
                [$meeting, '2026-10-07 12:00', 'Demo at restaurant', 'Tap flow tested on iPhone and Android. Both worked.', $us],
            ]],
            ['slug' => 'fec', 'name' => 'FEC ERP', 'client' => 'FEC Administration', 'desc' => 'College ERP for admission, fees, results and transcripts.', 'move' => $client, 'since' => '2026-10-13 12:00', 'cur' => 3, 'entries' => [
                [$delivery, '2026-10-13 12:00', 'UAT build shared', 'Admission, fees and results modules are live on the UAT server.', $client],
                [$dev, '2026-10-12 18:00', 'Results module', 'GPA calculation matches last semester’s result sheets.', $us],
                [$meeting, '2026-10-09 11:00', 'Registrar review', 'Registrar asked for a printable transcript format.', $us],
                [$call, '2026-10-05 10:00', 'Student data', 'Received the student list in Excel, 2,340 rows.', $us],
                [$note, '2026-10-02 16:00', 'Hosting', 'College IT will provide a VPS. Access pending.', $us],
            ]],
            ['slug' => 'shikhon', 'name' => 'Shikhon', 'client' => 'Shikhon Edtech', 'desc' => 'School and madrasa management SaaS.', 'move' => $us, 'since' => '2026-10-22 16:00', 'pinned' => true, 'meeting' => '2026-10-30 11:00', 'cur' => 2, 'entries' => [
                [$feedback, '2026-10-22 16:00', 'Attendance SMS', 'Wants an SMS to parents when a student is absent twice in a week.', $us],
                [$dev, '2026-10-21 12:00', 'Fee reports', 'Monthly fee collection report and due list, exportable to PDF.', $client],
                [$meeting, '2026-10-17 10:00', 'Weekly sync', 'Attendance and SMS come before the exam module.', $us],
                [$dev, '2026-10-13 15:00', 'Multi-branch', 'Branch switcher and per-branch roles done.', $us],
                [$call, '2026-10-08 11:30', 'Pilot pricing', 'Discussed per-student pricing for the pilot madrasas.', $us],
                [$note, '2026-10-04 14:00', 'Pilot list', 'Three madrasas in Chattogram agreed to pilot.', $us],
            ]],
            ['slug' => 'sirat', 'name' => 'Sirater Pothe', 'client' => 'Internal', 'desc' => 'Seerah timeline website.', 'move' => $none, 'since' => '2026-10-19 21:00', 'cur' => 5, 'entries' => [
                [$delivery, '2026-10-19 21:00', 'Chapter 3 published', 'Madinan period timeline with 38 events is live.', $none],
                [$dev, '2026-10-15 22:00', 'Hijrah map', 'Added the migration route map to the Hijrah chapter.', $us],
                [$note, '2026-10-10 20:00', 'Sources checked', 'Cross-checked dates against Ar-Raheeq Al-Makhtum.', $us],
                [$dev, '2026-10-05 21:30', 'Year scrubber', 'Smooth year scrubber on mobile.', $us],
            ]],
            ['slug' => 'ledger', 'name' => 'Income/Expense SaaS', 'client' => 'Internal product', 'desc' => 'Bookkeeping for madrasas.', 'move' => $us, 'since' => '2026-10-16 11:00', 'cur' => 2, 'entries' => [
                [$note, '2026-10-16 11:00', 'Paused for Shikhon', 'Paused to finish Shikhon fee reports. Resume with voucher printing.', $us],
                [$dev, '2026-10-13 17:00', 'Voucher entry', 'Income and expense vouchers with categories.', $us],
                [$call, '2026-10-09 12:00', 'Accountant interview', 'Spoke with a madrasa accountant about tracking Zakat funds separately.', $us],
                [$note, '2026-10-03 10:00', 'MVP scope', 'Vouchers, monthly summary and donor receipts.', $us],
            ]],
            ['slug' => 'bunny', 'name' => 'Bunny Kids World', 'client' => 'Bunny Kids World', 'desc' => 'Kids YouTube channel with animated rhymes and stories.', 'move' => $client, 'since' => '2026-10-21 19:00', 'meeting' => '2026-10-24 17:00', 'cur' => 2, 'roadmap' => ['Planning', 'Scripting', 'Production', 'Review', 'Publishing'], 'entries' => [
                [$delivery, '2026-10-21 19:00', 'Episode 12 draft', 'Sent the animated rhyme draft for review.', $client],
                [$dev, '2026-10-19 14:00', 'Character rigs', 'Finished rigs for Bunny and Toto.', $us],
                [$feedback, '2026-10-15 18:00', 'Thumbnail notes', 'Wants brighter thumbnails with larger faces.', $us],
                [$meeting, '2026-10-11 16:00', 'November plan', 'Planned 8 episodes for November.', $us],
                [$call, '2026-10-06 12:00', 'Voice artist', 'Booked the voice artist for two sessions a month.', $us],
            ]],
            // Archived
            ['slug' => 'dental', 'name' => 'Dhaka Dental Care', 'client' => 'Dr. Farhana Islam', 'desc' => 'Clinic website with appointment booking.', 'move' => $none, 'since' => '2026-09-12 15:00', 'archived_at' => '2026-09-12 15:00', 'cur' => 2, 'entries' => [
                [EntryType::Archived, '2026-09-12 15:00', 'Archived', 'Client paused budget until next year. Design and booking prototype are saved for a restart.', null],
                [$call, '2026-09-10 11:00', 'Budget on hold', 'Clinic is opening a second branch and paused all marketing spend.', $us],
                [$delivery, '2026-09-03 17:00', 'Booking prototype', 'Shared the clickable booking flow.', $client],
                [$dev, '2026-08-27 16:00', 'Booking flow', 'Doctor schedule and slot picker built.', $us],
                [$meeting, '2026-08-20 12:00', 'Kickoff', 'Agreed on 6 pages and online booking.', $us],
            ]],
            ['slug' => 'tours', 'name' => 'Green Leaf Tours', 'client' => 'Green Leaf Travels', 'desc' => 'Travel agency site with tour packages.', 'move' => $none, 'since' => '2026-10-09 13:00', 'archived_at' => '2026-10-09 13:00', 'cur' => 5, 'entries' => [
                [EntryType::Archived, '2026-10-09 13:00', 'Archived', 'Delivered and handed over. Their in-house team now maintains the site.', null],
                [$feedback, '2026-10-02 10:00', 'Sign-off', 'Client confirmed everything works and signed off.', $none],
                [$delivery, '2026-09-28 18:00', 'Site launched', 'Live on their domain with 24 tour packages.', $client],
                [$dev, '2026-09-20 15:00', 'Package pages', 'Itinerary, pricing and inquiry form for each package.', $us],
            ]],
        ];
    }

    /** @return list<array{string, ?string, ?string, bool}> */
    private function tasks(): array
    {
        return [
            ['Fix referral credit for bKash payments', 'ashes', '2026-10-24', false],
            ['Draft attendance SMS template', 'shikhon', '2026-10-22', false],
            ['Remind Royal Housing about v2 feedback', 'royal', '2026-10-23', false],
            ['Prepare notes for the Bunny Kids meeting', 'bunny', '2026-10-24', false],
            ['Renew Sirater Pothe domain', 'sirat', '2026-10-30', false],
            ['Send invoice reminders', null, null, false],
            ['Share FEC UAT logins with the registrar', 'fec', '2026-10-13', true],
        ];
    }
}
