<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\UserInterface;
use App\Domain\Factory\UserFactoryInterface;
use App\Infrastructure\Entity\User;

final class UserFactory implements UserFactoryInterface
{
    public function create(
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
    ): UserInterface {
        $user = new User;
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        return $user;
    }
}
