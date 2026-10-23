<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Creates (or updates) the one Pulse user from PULSE_EMAIL / PULSE_PASSWORD. */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('pulse.email');
        $password = config('pulse.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Set PULSE_EMAIL and PULSE_PASSWORD in .env before seeding the user.');
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Pulse', 'password' => $password],
        );
    }
}
