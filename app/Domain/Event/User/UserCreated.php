<?php

declare(strict_types=1);

namespace App\Domain\Event\User;

use App\Domain\Event\DomainEventInterface;
use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

final readonly class UserCreated implements DomainEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public UuidInterface $userUuid,
        public string $email,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): UuidInterface
    {
        return $this->userUuid;
    }
}
