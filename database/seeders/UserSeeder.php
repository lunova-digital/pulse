<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Creates (or updates) the owner account from PULSE_EMAIL / PULSE_PASSWORD. Team members are added in the app. */
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
            ['name' => 'Pulse', 'password' => $password, 'role' => Role::Owner, 'can_manage_clients' => true, 'is_active' => true],
        );
    }
}
