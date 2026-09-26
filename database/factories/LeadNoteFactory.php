<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

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
            // Belongs to whoever is signed in (tests act as the owner first), else a fresh account.
            'user_id' => fn () => Auth::id() ?? User::factory(),
            'lead_id' => fn (array $attributes) => Lead::factory()->state(['user_id' => $attributes['user_id']]),
            'body' => fake()->sentence(),
        ];
    }
}
