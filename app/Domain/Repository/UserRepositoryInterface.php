<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\UserInterface;
use Ramsey\Uuid\UuidInterface;

interface UserRepositoryInterface
{
    public function findByUuid(UuidInterface $uuid): ?UserInterface;

    public function findActiveByEmail(string $email): ?UserInterface;

    public function save(UserInterface $user): void;
}
