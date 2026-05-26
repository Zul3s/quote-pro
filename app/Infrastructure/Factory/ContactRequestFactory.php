<?php

declare(strict_types=1);

namespace App\Infrastructure\Factory;

use App\Domain\Entity\ContactRequestInterface;
use App\Domain\Factory\ContactRequestFactoryInterface;
use App\Domain\Model\Deadline;
use App\Domain\Model\RequestType;
use App\Infrastructure\Entity\ContactRequest;

final class ContactRequestFactory implements ContactRequestFactoryInterface
{
    public function create(
        string $name,
        string $email,
        RequestType $requestType,
        Deadline $deadline,
        string $description,
        ?string $phone = null,
        ?string $postalCode = null,
    ): ContactRequestInterface {
        $contactRequest = new ContactRequest;
        $contactRequest->setAttribute('name', $name);
        $contactRequest->setAttribute('email', $email);
        $contactRequest->setAttribute('request_type', $requestType);
        $contactRequest->setAttribute('deadline', $deadline);
        $contactRequest->setAttribute('description', $description);
        $contactRequest->setAttribute('phone', $phone);
        $contactRequest->setAttribute('postal_code', $postalCode);

        return $contactRequest;
    }
}
