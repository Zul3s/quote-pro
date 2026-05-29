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
     * Indicative French market prices per profession (main-d'œuvre, fournitures
     * en sus). Demo data only — realistic ballparks, not binding quotes.
     *
     * @var array<string, list<string>>
     */
    private const PRICING = [
        'plomberie' => [
            'Remplacement de robinetterie ~ 120 € / pièce',
            'Installation WC suspendu ~ 350 €',
            'Pose de chauffe-eau électrique ~ 400 €',
            'Recherche et réparation de fuite ~ 80 € / h',
        ],
        'chauffage' => [
            'Entretien annuel de chaudière ~ 120 € TTC',
            'Pose de chaudière gaz à condensation ~ 3 500 €',
            'Installation pompe à chaleur air/eau ~ 12 000 €',
            'Désembouage du circuit ~ 600 €',
        ],
        'électricité' => [
            "Pose d'une prise ou d'un interrupteur ~ 90 € / point",
            "Pose d'un point lumineux ~ 110 €",
            'Mise aux normes du tableau électrique ~ 1 200 €',
            'Tarif horaire ~ 45 € / h',
        ],
        'maçonnerie' => [
            'Montage de mur en parpaing ~ 60 € / m²',
            'Coulage de dalle béton ~ 80 € / m²',
            'Ouverture dans un mur porteur ~ 1 500 €',
        ],
        'menuiserie' => [
            'Pose de porte intérieure ~ 250 €',
            'Pose de fenêtre PVC ~ 500 € / unité',
            'Pose de parquet ~ 40 € / m²',
        ],
        'peinture' => [
            'Peinture murs et plafonds ~ 30 € / m²',
            'Pose de bande à joint ~ 15 € / ml',
            'Pose de faux plafond ~ 35 € / m²',
            'Pose de toile de verre ~ 25 € / m²',
        ],
        'carrelage' => [
            'Pose de carrelage au sol ~ 45 € / m²',
            'Pose de faïence murale ~ 50 € / m²',
            'Réalisation de chape ~ 35 € / m²',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $professions = fake()->randomElements(
            array_keys(self::PRICING),
            fake()->numberBetween(1, 3),
        );

        return [
            'uuid' => Uuid::uuid7()->toString(),
            'postal_code' => fake()->numerify('#####'),
            'professions' => $professions,
            // Always exposes a tariff grid aligned with the chosen professions,
            // so seeded profiles are never empty.
            'services' => self::pricingGrid($professions),
        ];
    }

    /**
     * Builds a human-readable tariff grid listing every priced service for the
     * given professions.
     *
     * @param  list<string>  $professions
     */
    public static function pricingGrid(array $professions): string
    {
        $lines = ['Grille tarifaire indicative (devis gratuit sous 48 h) :'];

        foreach ($professions as $profession) {
            foreach (self::PRICING[$profession] ?? [] as $entry) {
                $lines[] = '- '.$entry;
            }
        }

        return implode("\n", $lines);
    }
}
