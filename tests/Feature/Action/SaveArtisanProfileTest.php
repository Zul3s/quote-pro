<?php

declare(strict_types=1);

use App\Actions\SaveArtisanProfile;
use App\Data\SaveArtisanProfileData;
use App\Models\ArtisanProfile;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| SaveArtisanProfile Action
|--------------------------------------------------------------------------
*/

it('creates the singleton profile when none exists', function () {
    $profile = (new SaveArtisanProfile)->handle(SaveArtisanProfileData::fromValues(
        postalCode: '67000',
        professions: ['plâtrier', 'peintre'],
        services: 'Pose de faux plafond ~ 35 €/m².',
    ));

    expect($profile->uuid)->toBeUuid()
        ->and($profile->postal_code)->toBe('67000')
        ->and($profile->professions)->toBe(['plâtrier', 'peintre'])
        ->and($profile->services)->toBe('Pose de faux plafond ~ 35 €/m².');

    $this->assertDatabaseCount('artisan_profiles', 1);
    $this->assertDatabaseHas('artisan_profiles', [
        'postal_code' => '67000',
        'services' => 'Pose de faux plafond ~ 35 €/m².',
    ]);
});

it('updates the existing row instead of creating a second one', function () {
    $existing = ArtisanProfile::factory()->create([
        'postal_code' => '75011',
        'professions' => ['plomberie'],
        'services' => 'Ancien descriptif.',
    ]);

    $profile = (new SaveArtisanProfile)->handle(SaveArtisanProfileData::fromValues(
        postalCode: '69001',
        professions: ['électricité', 'chauffage'],
        services: 'Nouveau descriptif.',
    ));

    expect($profile->is($existing))->toBeTrue()
        ->and($profile->postal_code)->toBe('69001')
        ->and($profile->professions)->toBe(['électricité', 'chauffage']);

    $this->assertDatabaseCount('artisan_profiles', 1);
    $this->assertDatabaseHas('artisan_profiles', [
        'uuid' => $existing->uuid,
        'postal_code' => '69001',
        'services' => 'Nouveau descriptif.',
    ]);
});

it('stores a null services description when omitted', function () {
    $profile = (new SaveArtisanProfile)->handle(SaveArtisanProfileData::fromValues(
        postalCode: '13001',
        professions: ['maçonnerie'],
    ));

    expect($profile->services)->toBeNull();

    $this->assertDatabaseHas('artisan_profiles', [
        'postal_code' => '13001',
        'services' => null,
    ]);
});

it('rejects malformed input at the DTO boundary', function (callable $build) {
    expect($build)->toThrow(ValidationException::class);

    $this->assertDatabaseCount('artisan_profiles', 0);
})->with([
    'postal code with letters' => [fn () => SaveArtisanProfileData::fromValues('abcde', ['peintre'])],
    'postal code wrong length' => [fn () => SaveArtisanProfileData::fromValues('123', ['peintre'])],
    'no profession' => [fn () => SaveArtisanProfileData::fromValues('67000', [])],
    'profession too long' => [fn () => SaveArtisanProfileData::fromValues('67000', [str_repeat('a', 101)])],
    'services too long' => [fn () => SaveArtisanProfileData::fromValues('67000', ['peintre'], str_repeat('x', 5001))],
]);
