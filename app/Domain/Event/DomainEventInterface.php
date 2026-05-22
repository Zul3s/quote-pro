<?php

declare(strict_types=1);

namespace App\Domain\Event;

use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

interface DomainEventInterface
{
    public function occurredAt(): DateTimeImmutable;

    public function aggregateId(): ?UuidInterface;
}
