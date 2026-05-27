<?php

declare(strict_types=1);

namespace App\Application\UseCase\SubmitContactRequest;

use App\Domain\Entity\ContactRequestInterface;
use App\Domain\Event\ContactRequest\ContactRequestSubmitted;
use App\Domain\Event\EventDispatcherInterface;
use App\Domain\Factory\ContactRequestFactoryInterface;
use App\Domain\Repository\ContactRequestRepositoryInterface;
use App\Domain\Service\RequestValidatorInterface;

final readonly class UseCase
{
    public function __construct(
        private RequestValidatorInterface $validator,
        private ContactRequestFactoryInterface $contactRequestFactory,
        private ContactRequestRepositoryInterface $contactRequestRepository,
        private EventDispatcherInterface $events,
    ) {}

    public function execute(Request $request): ContactRequestInterface
    {
        $this->validator->validate($request);

        $contactRequest = $this->contactRequestFactory->create(
            name: $request->name,
            email: $request->email,
            requestType: $request->requestType,
            deadline: $request->deadline,
            description: $request->description,
            phone: $request->phone,
            postalCode: $request->postalCode,
        );

        $this->contactRequestRepository->save($contactRequest);

        $this->events->dispatch(new ContactRequestSubmitted(
            contactRequestUuid: $contactRequest->getUuid(),
            email: $contactRequest->getEmail(),
            requestType: $contactRequest->getRequestType(),
            deadline: $contactRequest->getDeadline(),
        ));

        return $contactRequest;
    }
}
