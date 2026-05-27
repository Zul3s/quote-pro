<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\CreateUser;
use App\Data\CreateUserData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class CreateUserController
{
    public function __invoke(Request $request, CreateUser $action): JsonResponse|RedirectResponse
    {
        $user = $action->handle(CreateUserData::fromRequest($request));

        if ($request->expectsJson()) {
            return new JsonResponse([
                'uuid' => $user->uuid,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
            ], 201);
        }

        return redirect()
            ->route('users.create')
            ->with('success', 'Utilisateur créé avec succès.');
    }
}
