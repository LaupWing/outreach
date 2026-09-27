<?php

namespace Database\Seeders;

use App\Enums\MailboxStatus;
use App\Enums\MailboxType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // A placeholder key so the demo account skips onboarding; replace it in Settings → Google.
        $user = User::factory()->create([
            'name' => 'Loc Nguyen',
            'email' => 'loc@snelstack.com',
            'google_places_key' => 'AIza'.str_repeat('x', 35),
        ]);

        $this->callWith(DemoSeeder::class, ['user' => $user]);

        // A verified account to work in: test@example.com / password. With SEED_* set in .env
        // it comes onboarded (real Places key + Gmail box); without them it starts at onboarding.
        $account = User::factory()->create([
            'name' => 'Test',
            'email' => 'test@example.com',
            'google_places_key' => config('services.seed.google_places_key'),
        ]);

        if (config('services.seed.mailbox_address') !== null && config('services.seed.mailbox_password') !== null) {
            $account->mailboxes()->create([
                'address' => config('services.seed.mailbox_address'),
                'type' => MailboxType::Imap,
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'username' => config('services.seed.mailbox_address'),
                'password' => config('services.seed.mailbox_password'),
                'status' => MailboxStatus::WarmingUp,
                'daily_limit' => 20,
                'warm_up_started_at' => now(),
                'connection_checked_at' => now(),
            ]);
        }

        // The same demo data, so there is something to look at right after a fresh seed.
        $this->callWith(DemoSeeder::class, ['user' => $account]);
    }
}
