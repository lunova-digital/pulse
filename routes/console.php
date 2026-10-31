<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Telegram reminders (need `scheduler: true` in .lunova.yml and the bot env values).
Schedule::command('pulse:telegram-digest')
    ->dailyAt(config('pulse.digest_time'))
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

// Repeats at these local hours while any project is still untouched; silent once all are cleared.
Schedule::command('pulse:telegram-untouched')
    ->hourly()
    ->timezone(config('app.timezone'))
    ->when(fn () => in_array((int) now(config('app.timezone'))->format('G'), config('pulse.untouched_hours'), true))
    ->withoutOverlapping();

Schedule::command('pulse:telegram-plan')
    ->dailyAt(config('pulse.plan_time'))
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('pulse:telegram-due')->everyFifteenMinutes()->withoutOverlapping();
