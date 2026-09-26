<?php

namespace Database\Factories;

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An account past onboarding: a Places key, and a mailbox if none exists yet.
     */
    public function onboarded(): static
    {
        return $this
            ->state(fn (array $attributes) => ['google_places_key' => 'AIza'.str_repeat('a', 35)])
            ->afterCreating(function (User $user): void {
                // Paused, so it never gets picked by the sender in a test that counts on its own boxes.
                if (! $user->mailboxes()->exists()) {
                    Mailbox::factory()->paused()->create(['user_id' => $user->id, 'address' => 'owner-'.$user->id.'@example.com']);
                }
            });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static {}
}
