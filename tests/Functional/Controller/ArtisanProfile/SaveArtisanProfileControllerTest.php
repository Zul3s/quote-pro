<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SaveArtisanProfileController — HTTP layer
|--------------------------------------------------------------------------
| Transport-level concerns only: JSON vs web response shape, validation →
| HTTP status, session flash. The singleton upsert behaviour (single row,
| create-then-update) and "writes nothing on failure" are covered in
| tests/Feature/Action/SaveArtisanProfileTest.php.
*/

it('returns a 201 JSON payload for API clients', function () {
    $response = $this->postJson('/profile', [
        'postalCode' => '67000',
        'professions' => ['plâtrier', 'peintre'],
        'services' => 'Pose de faux plafond ~ 35 €/m².',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['uuid', 'postal_code', 'professions', 'services'])
        ->assertJsonFragment([
            'postal_code' => '67000',
            'professions' => ['plâtrier', 'peintre'],
        ]);
});

it('returns 422 JSON when the postal code is malformed', function () {
    $response = $this->postJson('/profile', [
        'postalCode' => 'ABC',
        'professions' => ['plâtrier'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['postalCode']);
});

it('returns 422 JSON when no profession is provided', function () {
    $response = $this->postJson('/profile', [
        'postalCode' => '67000',
        'professions' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['professions']);
});

it('redirects to profile.edit with flash success on web form submission', function () {
    $response = $this->from('/profile')->post('/profile', [
        'postalCode' => '67000',
        'professions' => ['plâtrier'],
        'services' => null,
    ]);

    $response->assertRedirect('/profile')
        ->assertSessionHas('success');
});

it('redirects back with session errors on web form validation failure', function () {
    $response = $this->from('/profile')->post('/profile', [
        'postalCode' => '123',
        'professions' => [],
    ]);

    $response->assertRedirect('/profile')
        ->assertSessionHasErrors(['postalCode', 'professions']);
});
