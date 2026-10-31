<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'stage_id' => PipelineStage::visible()->value('id'),
            'last_activity_at' => now(),
        ];
    }

    public function inStage(string $name): static
    {
        return $this->state(fn () => ['stage_id' => PipelineStage::where('name', $name)->value('id')]);
    }

    public function followUp(\DateTimeInterface $at, bool $withTime = false): static
    {
        return $this->state(['next_followup_at' => $at, 'followup_has_time' => $withTime]);
    }
}
