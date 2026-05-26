<?php

declare(strict_types=1);

namespace App\Domain\Factory;

use App\Domain\Entity\ContactRequestInterface;
use App\Domain\Model\Deadline;
use App\Domain\Model\RequestType;

interface ContactRequestFactoryInterface
{
    public function create(
        string $name,
        string $email,
        RequestType $requestType,
        Deadline $deadline,
        string $description,
        ?string $phone = null,
        ?string $postalCode = null,
    ): ContactRequestInterface;
}
