<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\SaveArtisanProfileData;
use App\Models\ArtisanProfile;

final readonly class SaveArtisanProfile
{
    public function handle(SaveArtisanProfileData $data): ArtisanProfile
    {
        // Singleton upsert: the app is mono-artisan, so there is at most one row.
        $profile = ArtisanProfile::query()->first() ?? new ArtisanProfile;

        $profile->fill([
            'postal_code' => $data->postalCode,
            'professions' => $data->professions,
            'services' => $data->services,
        ])->save();

        return $profile;
    }
}
