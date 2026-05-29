<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ArtisanProfile;

/**
 * Read use case: returns the singleton artisan profile, or null when it has
 * never been saved. Mono-artisan, so there is at most one row.
 */
final readonly class ShowArtisanProfile
{
    public function handle(): ?ArtisanProfile
    {
        return ArtisanProfile::query()->first();
    }
}
