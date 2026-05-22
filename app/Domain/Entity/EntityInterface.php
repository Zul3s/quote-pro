<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Ramsey\Uuid\UuidInterface;

interface EntityInterface
{
    public function getUuid(): UuidInterface;
}
