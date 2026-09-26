<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
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
            'niche_id' => fn (array $attributes) => Niche::factory()->state(['user_id' => $attributes['user_id']]),
            'name' => fake()->randomElement(['Nieuwe website in 2 weken', 'Gratis snelheidscheck', 'Menukaart online + reserveren']),
            'description' => fake()->sentence(),
            'status' => OfferStatus::Idea,
        ];
    }

    /**
     * An offer that can be sent.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Active,
        ]);
    }
}
