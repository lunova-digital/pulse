<?php

namespace Database\Factories;

use App\Enums\WhoseMove;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'client_name' => fake()->company(),
            'description' => fake()->sentence(),
            'whose_move' => fake()->randomElement(WhoseMove::cases()),
            'whose_move_since' => now(),
            'last_activity_at' => now(),
            'is_pinned' => false,
        ];
    }

    public function waitingOn(WhoseMove $move): static
    {
        return $this->state(['whose_move' => $move]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
