<?php

declare(strict_types=1);

namespace App\Domain\Model;

final readonly class ValidationReason
{
    public function __construct(
        public string $messageKey,
        public ?string $propertyPath = null,
    ) {}
}
