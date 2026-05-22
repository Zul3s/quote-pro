<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

interface UserInterface extends EntityInterface
{
    public function getEmail(): string;

    public function setEmail(string $email): static;

    public function getFirstName(): ?string;

    public function setFirstName(?string $firstName): static;

    public function getLastName(): ?string;

    public function setLastName(?string $lastName): static;

    public function getDeletedAt(): ?DateTimeImmutable;

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static;
}
