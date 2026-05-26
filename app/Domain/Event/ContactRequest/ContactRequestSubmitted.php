<?php

declare(strict_types=1);

namespace App\Domain\Event\ContactRequest;

use App\Domain\Event\DomainEventInterface;
use App\Domain\Model\Deadline;
use App\Domain\Model\RequestType;
use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

final readonly class ContactRequestSubmitted implements DomainEventInterface
{
    private DateTimeImmutable $occurredAt;

    public function __construct(
        public UuidInterface $contactRequestUuid,
        public string $email,
        public RequestType $requestType,
        public Deadline $deadline,
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
        return $this->contactRequestUuid;
    }
}
