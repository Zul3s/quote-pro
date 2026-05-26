<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ContactRequestInterface;
use Ramsey\Uuid\UuidInterface;

interface ContactRequestRepositoryInterface
{
    public function findByUuid(UuidInterface $uuid): ?ContactRequestInterface;

    public function save(ContactRequestInterface $contactRequest): void;
}
