<?php

namespace Database\Factories;

use App\Enums\MilestoneState;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Milestone> */
class MilestoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->word(),
            'position' => 0,
            'state' => MilestoneState::Upcoming,
        ];
    }
}
