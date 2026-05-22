<?php

declare(strict_types=1);

namespace App\Domain\Specification;

use App\Domain\Model\ValidationReason;
use App\Domain\Repository\UserRepositoryInterface;

final class CanCreateUser extends AbstractSpecification
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function isSatisfiedBy(string $email, bool $exceptionMode = true): bool
    {
        $reasons = [];

        if ($this->userRepository->findActiveByEmail($email) !== null) {
            $reasons[] = new ValidationReason('validation.email.already_used', 'email');
        }

        return $this->toReturn($reasons, $exceptionMode);
    }
}
