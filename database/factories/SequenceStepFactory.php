<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\SequenceStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<SequenceStep>
 */
class SequenceStepFactory extends Factory
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
            'offer_id' => fn (array $attributes) => Offer::factory()->state(['user_id' => $attributes['user_id']]),
            'step' => 1,
            'days_after_previous' => 0,
            'subject' => '{{hook_subject}}',
            'body' => "Hoi,\n\n{{hook}}\n\nZal ik een voorbeeld sturen voor {{company}}?\n\nLoc",
        ];
    }

    /**
     * A follow-up, a few days after the step before it.
     */
    public function followUp(int $step): static
    {
        return $this->state(fn () => [
            'step' => $step,
            'days_after_previous' => 4,
            'subject' => 'Re: {{hook_subject}}',
            'body' => "Hoi,\n\nNog even hierop terugkomen.\n\nLoc",
        ]);
    }
}
