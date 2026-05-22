<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\User;

use App\Application\UseCase\CreateUser\Request;
use App\Application\UseCase\CreateUser\UseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;

final readonly class CreateUserController
{
    public function __invoke(Request $request, UseCase $useCase, HttpRequest $http): JsonResponse|RedirectResponse
    {
        $user = $useCase->execute($request);

        if ($http->expectsJson()) {
            return new JsonResponse([
                'uuid' => $user->getUuid()->toString(),
                'email' => $user->getEmail(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
            ], 201);
        }

        return redirect()
            ->route('users.create')
            ->with('success', 'Utilisateur créé avec succès.');
    }
}
