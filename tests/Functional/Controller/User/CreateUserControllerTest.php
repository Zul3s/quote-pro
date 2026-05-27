<?php

declare(strict_types=1);

use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| CreateUserController — HTTP / Inertia layer
|--------------------------------------------------------------------------
| Transport-level concerns: JSON vs Inertia response shape, validation →
| HTTP status, session flash. Business logic is covered in the Action test.
*/

it('returns a 201 JSON payload for API clients', function () {
    Event::fake([UserCreated::class]);

    $response = $this->postJson('/users', [
        'email' => 'http.user@example.com',
        'firstName' => 'Http',
        'lastName' => 'User',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['uuid', 'email', 'first_name', 'last_name'])
        ->assertJsonFragment(['email' => 'http.user@example.com']);
});

it('returns 422 JSON when the email is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/users', [
        'email' => 'taken@example.com',
        'firstName' => 'Dup',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 JSON when the payload is malformed', function () {
    $response = $this->postJson('/users', [
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('redirects back with session errors on web form duplicate', function () {
    User::factory()->create(['email' => 'taken-web@example.com']);

    $response = $this->from('/users/create')->post('/users', [
        'email' => 'taken-web@example.com',
        'firstName' => 'Dup',
    ]);

    $response->assertRedirect('/users/create')
        ->assertSessionHasErrors(['email']);
});

it('redirects to users.create with flash success on web form submission', function () {
    Event::fake([UserCreated::class]);

    $response = $this->from('/users/create')->post('/users', [
        'email' => 'web.user@example.com',
        'firstName' => 'Web',
        'lastName' => 'User',
    ]);

    $response->assertRedirect('/users/create')
        ->assertSessionHas('success');
});
