<?php

namespace Database\Seeders;

use App\Models\ArtisanProfile;
use App\Models\ContactRequest;
use App\Models\User;
use Database\Factories\ArtisanProfileFactory;
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

        // Mono-artisan application: a single profile row. The tariff grid is
        // rebuilt from the pinned professions so it stays consistent with them.
        $professions = ['plomberie', 'chauffage'];

        ArtisanProfile::factory()->create([
            'postal_code' => '67000',
            'professions' => $professions,
            'services' => ArtisanProfileFactory::pricingGrid($professions),
        ]);

        // A realistic follow-up queue: mostly qualified (varied priorities),
        // a few irrelevant (spam / out-of-scope), some still pending qualification,
        // and some that passed the soft gate with missing info acknowledged.
        ContactRequest::factory()->count(18)->qualified()->create();
        ContactRequest::factory()->count(6)->irrelevant()->create();
        ContactRequest::factory()->count(10)->unqualified()->create();
        ContactRequest::factory()->count(6)->missingInfoAcknowledged()->create();
    }
}
