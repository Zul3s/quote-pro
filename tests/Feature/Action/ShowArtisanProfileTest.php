<?php

declare(strict_types=1);

use App\Actions\ShowArtisanProfile;
use App\Models\ArtisanProfile;

/*
|--------------------------------------------------------------------------
| ShowArtisanProfile Action
|--------------------------------------------------------------------------
*/

it('returns null when no profile has been saved', function () {
    expect((new ShowArtisanProfile)->handle())->toBeNull();
});

it('returns the singleton profile with its stored attributes', function () {
    $existing = ArtisanProfile::factory()->create([
        'postal_code' => '67000',
        'professions' => ['plâtrier', 'peintre'],
        'services' => 'Pose de faux plafond ~ 35 €/m².',
    ]);

    $profile = (new ShowArtisanProfile)->handle();

    expect($profile)->not->toBeNull()
        ->and($profile->is($existing))->toBeTrue()
        ->and($profile->postal_code)->toBe('67000')
        ->and($profile->professions)->toBe(['plâtrier', 'peintre'])
        ->and($profile->services)->toBe('Pose de faux plafond ~ 35 €/m².');
});
