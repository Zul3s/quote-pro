<?php

declare(strict_types=1);

use App\Application\UseCase\SendWelcomeEmail\Request;
use App\Application\UseCase\SendWelcomeEmail\UseCase;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Service\MailerInterface;
use App\Infrastructure\Entity\User;
use Ramsey\Uuid\Uuid;

it('sends a welcome email to an existing user', function () {
    $user = User::factory()->create([
        'email' => 'welcome@example.com',
        'first_name' => 'Welcome',
        'last_name' => 'User',
    ]);

    $fakeMailer = new class implements MailerInterface
    {
        /** @var list<array<string, mixed>> */
        public array $calls = [];

        public function send(string $toEmail, ?string $toName, string $subject, string $view, array $context = []): void
        {
            $this->calls[] = compact('toEmail', 'toName', 'subject', 'view', 'context');
        }
    };

    $this->app->instance(MailerInterface::class, $fakeMailer);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);
    $useCase->execute(new Request(userUuid: $user->getUuid()->toString()));

    expect($fakeMailer->calls)->toHaveCount(1);
    expect($fakeMailer->calls[0]['toEmail'])->toBe('welcome@example.com');
    expect($fakeMailer->calls[0]['toName'])->toBe('Welcome User');
    expect($fakeMailer->calls[0]['subject'])->toBe('Bienvenue sur Quote Plus !');
    expect($fakeMailer->calls[0]['view'])->toBe('emails.welcome');
    expect($fakeMailer->calls[0]['context'])->toMatchArray([
        'firstName' => 'Welcome',
        'lastName' => 'User',
        'email' => 'welcome@example.com',
    ]);
});

it('builds the recipient name from first name only when last name is missing', function () {
    $user = User::factory()->create([
        'email' => 'partial@example.com',
        'first_name' => 'Partial',
        'last_name' => null,
    ]);

    $fakeMailer = new class implements MailerInterface
    {
        public ?string $capturedName = 'sentinel';

        public function send(string $toEmail, ?string $toName, string $subject, string $view, array $context = []): void
        {
            $this->capturedName = $toName;
        }
    };

    $this->app->instance(MailerInterface::class, $fakeMailer);

    app(UseCase::class)->execute(new Request(userUuid: $user->getUuid()->toString()));

    expect($fakeMailer->capturedName)->toBe('Partial');
});

it('throws when the user does not exist', function () {
    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    expect(fn () => $useCase->execute(new Request(
        userUuid: Uuid::uuid7()->toString(),
    )))->toThrow(EntityNotFoundException::class);
});
