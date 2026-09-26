<?php

namespace Database\Factories;

use App\Enums\ScrapeRunStatus;
use App\Models\Niche;
use App\Models\ScrapeRun;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'niche_id' => Niche::factory(),
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
