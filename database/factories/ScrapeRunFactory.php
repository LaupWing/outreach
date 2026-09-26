<?php

namespace Database\Factories;

use App\Enums\ScrapeRunStatus;
use App\Models\Niche;
use App\Models\ScrapeRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<ScrapeRun>
 */
class ScrapeRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $found = fake()->numberBetween(20, 60);

        return [
            // Belongs to whoever is signed in (tests act as the owner first), else a fresh account.
            'user_id' => fn () => Auth::id() ?? User::factory(),
            'niche_id' => fn (array $attributes) => Niche::factory()->state(['user_id' => $attributes['user_id']]),
            'query' => fake()->randomElement(['tandarts', 'fysiotherapie', 'restaurant', 'advocaat']),
            'place' => fake()->randomElement(['Amsterdam', 'Utrecht', 'Rotterdam', 'Haarlem', 'Den Haag']),
            'status' => ScrapeRunStatus::Done,
            'requests' => (int) ceil($found / 20),
            'found' => $found,
            'with_email' => fake()->numberBetween(0, $found),
            'blocked' => fake()->numberBetween(0, 5),
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(10),
        ];
    }

    /**
     * A run that is still going.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScrapeRunStatus::Running,
            'finished_at' => null,
        ]);
    }
}
