<?php

namespace Database\Factories;

use App\Models\ArtisanProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;

/**
 * @extends Factory<ArtisanProfile>
 */
class ArtisanProfileFactory extends Factory
{
    protected $model = ArtisanProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid7()->toString(),
            'postal_code' => fake()->numerify('#####'),
            'professions' => fake()->randomElements(
                ['plomberie', 'chauffage', 'électricité', 'maçonnerie', 'menuiserie', 'peinture', 'carrelage'],
                fake()->numberBetween(1, 3),
            ),
            'services' => fake()->optional()->paragraph(),
        ];
    }
}
