/**
 * Mirrors the backend contract for the artisan profile:
 * @see app/Data/SaveArtisanProfileData.php
 * @see app/Http/Controllers/ArtisanProfile/EditArtisanProfileController.php
 */

export type ArtisanProfilePayload = {
    postalCode: string;
    professions: string[];
    services: string;
};

/**
 * Shape of the `profile` Inertia prop rendered by EditArtisanProfileController.
 * `null` when no profile has been saved yet.
 */
export type ArtisanProfileProps = {
    postalCode: string;
    professions: string[];
    services: string | null;
} | null;
