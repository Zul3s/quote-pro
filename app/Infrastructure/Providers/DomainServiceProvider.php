<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Event\EventDispatcherInterface;
use App\Domain\Event\User\UserCreated;
use App\Domain\Factory\ContactRequestFactoryInterface;
use App\Domain\Factory\UserFactoryInterface;
use App\Domain\Repository\ContactRequestRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\MailerInterface;
use App\Domain\Service\RequestValidatorInterface;
use App\Infrastructure\Event\LaravelEventDispatcher;
use App\Infrastructure\Factory\ContactRequestFactory;
use App\Infrastructure\Factory\UserFactory;
use App\Infrastructure\Job\SendWelcomeEmail;
use App\Infrastructure\Repository\EloquentContactRequestRepository;
use App\Infrastructure\Repository\EloquentUserRepository;
use App\Infrastructure\Service\Mailer\LaravelMailer;
use App\Infrastructure\Service\Validator\SpatieRequestValidator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

/**
 * Binds Domain interfaces to their Infrastructure implementations.
 *
 * One-stop wiring file for the DDD architecture — anywhere a Use Case asks
 * for a Domain interface, the Container resolves the corresponding Eloquent
 * implementation defined here.
 */
final class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class => EloquentUserRepository::class,
        UserFactoryInterface::class => UserFactory::class,
        ContactRequestRepositoryInterface::class => EloquentContactRequestRepository::class,
        ContactRequestFactoryInterface::class => ContactRequestFactory::class,
        EventDispatcherInterface::class => LaravelEventDispatcher::class,
        MailerInterface::class => LaravelMailer::class,
        RequestValidatorInterface::class => SpatieRequestValidator::class,
    ];

    public function boot(Dispatcher $events): void
    {
        // Map Domain Events → async Jobs (équivalent des EventSubscriber Symfony)
        $events->listen(UserCreated::class, function (UserCreated $event): void {
            SendWelcomeEmail::dispatch($event->userUuid->toString());
        });
    }
}
