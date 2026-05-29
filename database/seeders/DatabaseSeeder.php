<?php

namespace Database\Seeders;

use App\Models\ArtisanProfile;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo data.
     *
     * Demo seeding is restricted to non-production environments: production data
     * never depends on fixtures. Reference data that must exist in production
     * belongs in migrations, not here.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

        // Mono-artisan application: a single profile row.
        ArtisanProfile::factory()->create([
            'postal_code' => '67000',
            'professions' => ['plomberie', 'chauffage'],
        ]);

        ContactRequest::factory()->count(15)->create();
    }
}
