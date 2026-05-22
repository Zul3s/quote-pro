<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class EntityNotFoundException extends RuntimeException
{
    public static function forClass(string $class, string $identifier): self
    {
        return new self(sprintf('Entity %s with identifier %s not found.', $class, $identifier));
    }
}
