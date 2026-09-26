<?php

namespace Database\Factories;

use App\Enums\NicheStatus;
use App\Models\Niche;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Niche>
 */
class NicheFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Tandartsen', 'Fysiotherapeuten', 'Restaurants', 'Advocatenkantoren', 'Kappers', 'Makelaars', 'Accountants']),
            'status' => NicheStatus::Idea,
            'why' => fake()->sentence(),
            'findings' => null,
        ];
    }

    /**
     * A niche we are mailing right now.
     */
    public function testing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => NicheStatus::Testing,
            'findings' => fake()->paragraph(),
        ]);
    }
}
