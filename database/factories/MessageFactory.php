<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'mailbox_id' => Mailbox::factory(),
            'step' => 1,
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'status' => MessageStatus::Sent,
            'thread_id' => 'thr_'.fake()->unique()->lexify('?????'),
            'sent_at' => now()->subDays(2),
            'reply_body' => null,
            'reply_received_at' => null,
        ];
    }

    /**
     * The lead wrote back.
     */
    public function replied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::Replied,
            'reply_body' => 'Stuur maar een voorbeeld.',
            'reply_received_at' => now()->subDay(),
        ]);
    }

    /**
     * The address did not exist.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::Bounced,
        ]);
    }
}
