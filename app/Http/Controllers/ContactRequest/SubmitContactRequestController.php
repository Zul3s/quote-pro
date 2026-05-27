<?php

declare(strict_types=1);

namespace App\Http\Controllers\ContactRequest;

use App\Actions\SubmitContactRequest;
use App\Data\SubmitContactRequestData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class SubmitContactRequestController
{
    public function __invoke(Request $request, SubmitContactRequest $action): JsonResponse|RedirectResponse
    {
        $contactRequest = $action->handle(SubmitContactRequestData::fromRequest($request));

        if ($request->expectsJson()) {
            return new JsonResponse([
                'uuid' => $contactRequest->uuid,
                'name' => $contactRequest->name,
                'email' => $contactRequest->email,
                'request_type' => $contactRequest->request_type->value,
                'deadline' => $contactRequest->deadline->value,
            ], 201);
        }

        return redirect()
            ->route('thank-you')
            ->with('success', 'Merci, votre demande a bien été reçue.');
    }
}
