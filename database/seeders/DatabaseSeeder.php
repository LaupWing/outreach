<?php

namespace Database\Seeders;

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

        // An empty, verified account to walk through onboarding with: test@example.com / password.
        User::factory()->create([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);
    }
}
