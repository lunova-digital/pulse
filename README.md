# Pulse

A small-team project-status tracker with a daily priority list, a client follow-up funnel and Telegram reminders. Laravel + Livewire 3 + Tailwind 4, SQLite by default.

## Setup

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set the owner login in `.env`:

```
PULSE_EMAIL=you@yourdomain.com
PULSE_PASSWORD=a-long-password
```

Then:

```sh
php artisan migrate
php artisan db:seed --class=UserSeeder           # creates/updates the one user from PULSE_EMAIL / PULSE_PASSWORD
php artisan db:seed --class=SampleProjectsSeeder # optional: demo data from the design
npm run build                                    # or `npm run dev` while developing
php artisan serve
php artisan test
```

`php artisan db:seed` runs every seeder: owner, sample projects/products/tasks, sample clients, and demo people (`member@pulse.test`, `sales@pulse.test` with Clients access, `client@pulse.test` read-only portal user, deactivated `former@pulse.test`; all with password `password`) plus priorities, skip history and a converted client. Never run it in production, where only `UserSeeder` runs. Re-running `UserSeeder` after editing `.env` updates the password.
There is no public registration: the owner adds people on the **Team** screen. Set `APP_TIMEZONE` (e.g. `Asia/Dhaka`) so "today", stale and meeting
windows follow your day.

## Switching to MySQL (production)

In `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pulse
DB_USERNAME=...
DB_PASSWORD=...
```

Create the database, run `php artisan migrate --force` and `php artisan db:seed --class=UserSeeder --force`
(skip the sample-data seeder), then `npm run build` and `php artisan config:cache`.

## Tabs

- **Projects** and **Products** (same thing, different kind): the pin button makes an item *today's priority*. Priority items are listed first and stay pinned from day to day until you change them, so if you set nothing the previous priority carries over. Every active item must be **touched** each day: post an update, or press **Skip today** on its card (or **Select** several and skip in bulk; priority items are skipped one by one only). A skipped item disappears until tomorrow. Each skip is its own row (who, which item, which day) in a collapsed *Skip history*, on the list and on the item's page. The banner shows how many items are still untouched.
- **Today**, **Tasks**, **Archived**: what needs attention now, loose to-dos, and finished work.
- **Clients**: a funnel with editable stages. Every logged conversation needs a next follow-up date (or "no follow-up needed"). *Start project* creates a project under the client's name and moves them to the won stage.
- **Team** (owner only): add team members and client-portal users and pick exactly which projects and products each can open.

## Roles

| Role | Sees | Can do |
|---|---|---|
| Owner | everything | everything, manages the Team |
| Team member | only assigned projects/products and their tasks | view, edit, post updates, create items (auto-assigned); Clients tab only if enabled |
| Client | only assigned projects, read-only | no navigation; updates ticked *Internal only* are hidden from them |

Deactivated users cannot sign in; their history is kept.

## Telegram reminders

1. Create a bot with @BotFather, add it to the team group, and get the group's chat id.
2. Set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` (and optionally `PULSE_DIGEST_TIME`, default 09:00, and `PULSE_PLAN_TIME`, default 22:00, both in `APP_TIMEZONE`).
3. The scheduler must run: locally `php artisan schedule:work`; in production something must run `php artisan schedule:run` every minute (the Lunova manifest sets `scheduler: true`).

`pulse:telegram-digest` posts the morning message (follow-ups due today, overdue ones, today's priority). `pulse:telegram-plan` sends the evening reminder (default 22:00) to set tomorrow's priority, and `pulse:telegram-untouched` the not-touched list, both to your personal chat (`TELEGRAM_OWNER_CHAT_ID`; open the bot and press Start first), or to the group if that is not set, listing what stays priority if nothing changes. `pulse:telegram-untouched` runs at `PULSE_UNTOUCHED_HOURS` (default 11,14,17,20) and lists every project with no update or skip today, until all are cleared. `pulse:telegram-due` pings when a follow-up with a set time arrives, once per time. Nothing is sent while the bot is not configured.

## Rules baked in

- Projects are never deleted (`Project::delete()` throws; no delete routes). Archive and reopen require a reason.
- Timeline entries, client history and skips are append-only (edit and delete throw). Clients and pipeline stages are never deleted either.
