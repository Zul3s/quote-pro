<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Model\Deadline;
use App\Domain\Model\RequestType;
use DateTimeImmutable;

interface ContactRequestInterface extends EntityInterface
{
    public function getName(): string;

    public function getEmail(): string;

    public function getPhone(): ?string;

    public function getRequestType(): RequestType;

    public function getDeadline(): Deadline;

    public function getPostalCode(): ?string;

    public function getDescription(): string;

    public function getCreatedAt(): DateTimeImmutable;
}
