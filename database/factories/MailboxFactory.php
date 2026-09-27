<?php

namespace Database\Factories;

use App\Enums\MailboxStatus;
use App\Enums\MailboxType;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

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
            // Belongs to whoever is signed in (tests act as the owner first), else a fresh account.
            'user_id' => fn () => Auth::id() ?? User::factory(),
            'address' => fake()->unique()->safeEmail(),
            'type' => MailboxType::Imap,
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'username' => fn (array $attributes) => $attributes['address'],
            'password' => 'app-password',
            'status' => MailboxStatus::Active,
            'daily_limit' => 40,
            'sent_today' => 0,
            'sent_today_on' => null,
            'warm_up_started_at' => null,
            // A factory box passed its check; the sender and the inbox check trust it.
            'connection_checked_at' => now(),
            'connection_error' => null,
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
