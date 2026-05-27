<?php

declare(strict_types=1);

use App\Actions\CreateUser;
use App\Data\CreateUserData;
use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| CreateUser Action
|--------------------------------------------------------------------------
| Orchestration + business rules only. HTTP transport is covered in
| tests/Functional/Controller/User/.
*/

it('creates a user with valid data and dispatches UserCreated', function () {
    Event::fake([UserCreated::class]);

    $user = (new CreateUser)->handle(
        CreateUserData::fromValues('jane.doe@example.com', 'Jane', 'Doe'),
    );

    expect($user->email)->toBe('jane.doe@example.com')
        ->and($user->first_name)->toBe('Jane')
        ->and($user->last_name)->toBe('Doe')
        ->and($user->uuid)->toBeUuid();

    $this->assertDatabaseHas('users', [
        'email' => 'jane.doe@example.com',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    Event::assertDispatched(UserCreated::class, fn (UserCreated $event) => $event->user->is($user));
});

it('rejects a duplicate email via the EmailIsUnique rule', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    expect(fn () => (new CreateUser)->handle(
        CreateUserData::fromValues('duplicate@example.com', 'Dup'),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('users', 1);
});
