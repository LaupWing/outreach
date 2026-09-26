<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\Niche;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'niche_id' => Niche::factory(),
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
