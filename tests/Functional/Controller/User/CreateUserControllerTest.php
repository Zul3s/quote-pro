<?php

declare(strict_types=1);

use App\Domain\Event\User\UserCreated;
use App\Infrastructure\Entity\User;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| CreateUserController — HTTP / Inertia layer
|--------------------------------------------------------------------------
| Covers transport-level concerns: JSON vs Inertia response shape, Domain
| exception → HTTP status translation (via bootstrap/app.php), session
| flash. Business logic itself is covered in CreateUser Use Case test.
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

it('returns 422 JSON when a Domain validation fails on API call', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/users', [
        'email' => 'taken@example.com',
        'firstName' => 'Dup',
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
