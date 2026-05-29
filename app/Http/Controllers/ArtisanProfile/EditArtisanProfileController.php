<?php

declare(strict_types=1);

namespace App\Http\Controllers\ArtisanProfile;

use App\Actions\ShowArtisanProfile;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EditArtisanProfileController
{
    public function __invoke(ShowArtisanProfile $action): Response
    {
        $profile = $action->handle();

        return Inertia::render('settings/profile', [
            'profile' => $profile === null ? null : [
                'postalCode' => $profile->postal_code,
                'professions' => $profile->professions,
                'services' => $profile->services,
            ],
        ]);
    }
}
