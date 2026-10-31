# Pulse

A small-team project-status tracker with a daily check-in, a client follow-up funnel and Telegram reminders. Laravel + Livewire 3 + Tailwind 4, SQLite by default.

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

- **Daily**: every active project and product is checked in each day by posting an update, or skipped (the skip is the check-in). Items pinned as *priority for today* (and tasks flagged High, or due) need an update; skipping them takes two confirmations and cannot be done in bulk. Other items can be skipped one by one or in bulk. Skips are listed in a collapsed *Skip history*. Use the calendar button on a row to make it tomorrow's priority.
- **Today**, **Projects**, **Products**, **Tasks**, **Archived**: projects and products are the same thing with a different kind.
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
2. Set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID` (and optionally `PULSE_DIGEST_TIME`, default 09:00 in `APP_TIMEZONE`).
3. The scheduler must run: locally `php artisan schedule:work`; in production something must run `php artisan schedule:run` every minute (the Lunova manifest sets `scheduler: true`).

`pulse:telegram-digest` posts the morning message (follow-ups due today, overdue ones, priorities without an update). `pulse:telegram-due` pings when a follow-up with a set time arrives, once per time. Nothing is sent while the bot is not configured.

## Rules baked in

- Projects are never deleted (`Project::delete()` throws; no delete routes). Archive and reopen require a reason.
- Timeline entries, client history and skips are append-only (edit and delete throw). Clients and pipeline stages are never deleted either.
