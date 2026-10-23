<?php

namespace Database\Factories;

use App\Enums\EntryType;
use App\Models\Project;
use App\Models\TimelineEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TimelineEntry> */
class TimelineEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'type' => fake()->randomElement(EntryType::postable()),
            'status_label' => fake()->words(3, true),
            'note' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
