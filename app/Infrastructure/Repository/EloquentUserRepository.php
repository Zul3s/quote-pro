<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\UserInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Entity\User;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByUuid(UuidInterface $uuid): ?UserInterface
    {
        return User::query()
            ->where('uuid', $uuid->toString())
            ->first();
    }

    public function findActiveByEmail(string $email): ?UserInterface
    {
        // SoftDeletes trait already filters out trashed users by default.
        return User::query()
            ->where('email', $email)
            ->first();
    }

    public function save(UserInterface $user): void
    {
        if (! $user instanceof User) {
            throw new InvalidArgumentException(sprintf(
                'EloquentUserRepository expects %s, %s given.',
                User::class,
                $user::class,
            ));
        }

        $user->save();
    }
}
