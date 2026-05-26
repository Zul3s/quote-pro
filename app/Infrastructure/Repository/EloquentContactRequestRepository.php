<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\ContactRequestInterface;
use App\Domain\Repository\ContactRequestRepositoryInterface;
use App\Infrastructure\Entity\ContactRequest;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;

final class EloquentContactRequestRepository implements ContactRequestRepositoryInterface
{
    public function findByUuid(UuidInterface $uuid): ?ContactRequestInterface
    {
        return ContactRequest::query()
            ->where('uuid', $uuid->toString())
            ->first();
    }

    public function save(ContactRequestInterface $contactRequest): void
    {
        if (! $contactRequest instanceof ContactRequest) {
            throw new InvalidArgumentException(sprintf(
                'EloquentContactRequestRepository expects %s, %s given.',
                ContactRequest::class,
                $contactRequest::class,
            ));
        }

        $contactRequest->save();
    }
}
