<?php

declare(strict_types=1);

namespace App\Application\UseCase\CreateUser;

use App\Domain\Entity\UserInterface;
use App\Domain\Event\EventDispatcherInterface;
use App\Domain\Event\User\UserCreated;
use App\Domain\Factory\UserFactoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Specification\CanCreateUser;

final readonly class UseCase
{
    public function __construct(
        private UserFactoryInterface $userFactory,
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $events,
        private CanCreateUser $canCreateUser,
    ) {}

    public function execute(Request $request): UserInterface
    {
        $this->canCreateUser->isSatisfiedBy($request->email);

        $user = $this->userFactory->create(
            email: $request->email,
            firstName: $request->firstName,
            lastName: $request->lastName,
        );

        $this->userRepository->save($user);

        $this->events->dispatch(new UserCreated(
            userUuid: $user->getUuid(),
            email: $user->getEmail(),
        ));

        return $user;
    }
}
