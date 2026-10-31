<?php

return [
    // Credentials for the single Pulse user (see UserSeeder).
    'email' => env('PULSE_EMAIL', 'you@example.com'),
    'password' => env('PULSE_PASSWORD'),

    // Local time (APP_TIMEZONE) of the daily Telegram digest, HH:MM.
    'digest_time' => env('PULSE_DIGEST_TIME', '09:00'),

    // Local time of the evening "set tomorrow's priority" reminder, HH:MM.
    'plan_time' => env('PULSE_PLAN_TIME', '22:00'),
];
