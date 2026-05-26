<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\ContactRequest;

use App\Application\UseCase\SubmitContactRequest\Request;
use App\Application\UseCase\SubmitContactRequest\UseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;

final readonly class SubmitContactRequestController
{
    public function __invoke(Request $request, UseCase $useCase, HttpRequest $http): JsonResponse|RedirectResponse
    {
        $contactRequest = $useCase->execute($request);

        if ($http->expectsJson()) {
            return new JsonResponse([
                'uuid' => $contactRequest->getUuid()->toString(),
                'name' => $contactRequest->getName(),
                'email' => $contactRequest->getEmail(),
                'request_type' => $contactRequest->getRequestType()->value,
                'deadline' => $contactRequest->getDeadline()->value,
            ], 201);
        }

        return redirect()
            ->route('thank-you')
            ->with('success', 'Merci, votre demande a bien été reçue.');
    }
}
