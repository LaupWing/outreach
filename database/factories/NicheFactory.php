<?php

namespace Database\Factories;

use App\Enums\NicheStatus;
use App\Models\Niche;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

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
            // Belongs to whoever is signed in (tests act as the owner first), else a fresh account.
            'user_id' => fn () => Auth::id() ?? User::factory(),
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
