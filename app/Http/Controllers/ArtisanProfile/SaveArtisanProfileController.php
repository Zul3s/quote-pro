<?php

declare(strict_types=1);

namespace App\Http\Controllers\ArtisanProfile;

use App\Actions\SaveArtisanProfile;
use App\Data\SaveArtisanProfileData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class SaveArtisanProfileController
{
    public function __invoke(Request $request, SaveArtisanProfile $action): JsonResponse|RedirectResponse
    {
        $profile = $action->handle(SaveArtisanProfileData::fromRequest($request));

        if ($request->expectsJson()) {
            return new JsonResponse([
                'uuid' => $profile->uuid,
                'postal_code' => $profile->postal_code,
                'professions' => $profile->professions,
                'services' => $profile->services,
            ], 201);
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Profil enregistré avec succès.');
    }
}
