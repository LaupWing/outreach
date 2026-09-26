<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\SequenceStep;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'offer_id' => Offer::factory(),
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
