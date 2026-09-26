<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Niche;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'niche_id' => Niche::factory(),
            'offer_id' => null,
            'scrape_run_id' => null,
            'company' => $company,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => fake()->domainName(),
            'city' => fake()->city(),
            'status' => LeadStatus::New,
            'source' => LeadSource::Places,
            'hook' => null,
            'signals' => [
                'copyright_year' => fake()->numberBetween(2015, 2024),
                'viewport' => fake()->boolean(80),
                'software' => [fake()->randomElement(['WordPress', 'Wix', 'Joomla', 'Squarespace'])],
                'last_news_at' => null,
                'blocked' => false,
                'javascript_only' => false,
            ],
            'last_contact_at' => null,
            'next_action_at' => null,
        ];
    }

    /**
     * A lead that got its first mail and is due for a follow-up.
     */
    public function emailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::Emailed,
            'hook' => 'Copyright staat nog op 2017 en de site is niet mobiel.',
            'last_contact_at' => now()->subDays(4),
            'next_action_at' => now(),
        ]);
    }

    /**
     * A site the enricher could not read.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'signals' => [...$attributes['signals'], 'blocked' => true],
        ]);
    }
}
