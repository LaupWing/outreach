<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadNote>
 */
class LeadNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
