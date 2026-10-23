# Pulse

A personal, single-user project-status tracker. Laravel + Livewire 3 + Tailwind 4, SQLite by default.

## Setup

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set your login in `.env`:

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

`php artisan db:seed` runs both seeders. Re-running `UserSeeder` after editing `.env` updates the password.
There is no public registration. Set `APP_TIMEZONE` (e.g. `Asia/Dhaka`) so "today", stale and meeting
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

## Rules baked in

- Projects are never deleted (`Project::delete()` throws; no delete routes). Archive and reopen require a reason.
- Timeline entries are append-only (edit and delete throw).
