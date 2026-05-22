<?php

declare(strict_types=1);

use App\Application\UseCase\CreateUser\Request;
use App\Application\UseCase\CreateUser\UseCase;
use App\Domain\Entity\UserInterface;
use App\Domain\Event\User\UserCreated;
use App\Domain\Exception\ValidationsException;
use App\Infrastructure\Entity\User;
use App\Infrastructure\Job\SendWelcomeEmail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| CreateUser Use Case
|--------------------------------------------------------------------------
| Tests of the Application Use Case only. HTTP/transport concerns are
| covered separately in tests/Feature/Http/Controller/User/.
*/

it('creates a user with valid data', function () {
    Event::fake([UserCreated::class]);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    $user = $useCase->execute(new Request(
        email: 'jane.doe@example.com',
        firstName: 'Jane',
        lastName: 'Doe',
    ));

    expect($user)->toBeInstanceOf(UserInterface::class);
    expect($user->getEmail())->toBe('jane.doe@example.com');
    expect($user->getFirstName())->toBe('Jane');
    expect($user->getLastName())->toBe('Doe');
    expect($user->getUuid()->toString())->toBeUuid();

    $this->assertDatabaseHas('users', [
        'email' => 'jane.doe@example.com',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    Event::assertDispatched(UserCreated::class, function (UserCreated $event) use ($user) {
        return $event->userUuid->equals($user->getUuid())
            && $event->email === $user->getEmail();
    });
});

it('rejects a duplicate email via specification', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    expect(fn () => $useCase->execute(new Request(
        email: 'duplicate@example.com',
        firstName: 'Dup',
    )))->toThrow(ValidationsException::class);

    $this->assertDatabaseCount('users', 1);
});

it('dispatches the SendWelcomeEmail job when a user is created', function () {
    Bus::fake([SendWelcomeEmail::class]);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);
    $user = $useCase->execute(new Request(
        email: 'wired@example.com',
        firstName: 'Wired',
    ));

    Bus::assertDispatched(
        SendWelcomeEmail::class,
        fn (SendWelcomeEmail $job) => $job->userUuid === $user->getUuid()->toString(),
    );
});
