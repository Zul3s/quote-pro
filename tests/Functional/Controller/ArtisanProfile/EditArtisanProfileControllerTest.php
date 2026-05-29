<?php

declare(strict_types=1);

use App\Models\ArtisanProfile;
use Inertia\Testing\AssertableInertia as Assert;

/*
|--------------------------------------------------------------------------
| EditArtisanProfileController — HTTP / Inertia layer
|--------------------------------------------------------------------------
| Transport-level concerns only: the settings/profile page renders with the
| current profile prop (null when empty, camelCase payload when set). The
| read behaviour itself is covered in tests/Feature/Action/ShowArtisanProfileTest.php.
*/

it('renders the profile page with a null profile when none exists', function () {
    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('profile', null)
        );
});

it('renders the profile page prefilled with the saved profile', function () {
    ArtisanProfile::factory()->create([
        'postal_code' => '67000',
        'professions' => ['plâtrier', 'peintre'],
        'services' => 'Pose de faux plafond ~ 35 €/m².',
    ]);

    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('profile.postalCode', '67000')
            ->where('profile.professions', ['plâtrier', 'peintre'])
            ->where('profile.services', 'Pose de faux plafond ~ 35 €/m².')
        );
});
