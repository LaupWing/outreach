<?php

namespace Database\Factories;

use App\Enums\MailboxStatus;
use App\Enums\MailboxType;
use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'address' => fake()->unique()->safeEmail(),
            'type' => MailboxType::Gmail,
            'status' => MailboxStatus::Active,
            'daily_limit' => 40,
            'sent_today' => 0,
            'sent_today_on' => null,
            'warm_up_started_at' => null,
        ];
    }

    /**
     * A fresh address that ramps its limit over two weeks.
     */
    public function warmingUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MailboxStatus::WarmingUp,
            'daily_limit' => 20,
            'warm_up_started_at' => now()->subDays(3),
        ]);
    }

    /**
     * A box that is out of the rotation.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MailboxStatus::Paused,
        ]);
    }
}
