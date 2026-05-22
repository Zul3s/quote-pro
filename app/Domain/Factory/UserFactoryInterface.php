<?php

declare(strict_types=1);

namespace App\Domain\Factory;

use App\Domain\Entity\UserInterface;

interface UserFactoryInterface
{
    public function create(
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
    ): UserInterface;
}
