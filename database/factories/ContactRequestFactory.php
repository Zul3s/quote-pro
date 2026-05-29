<?php

namespace Database\Factories;

use App\Enums\Deadline;
use App\Enums\LeadQuality;
use App\Enums\Priority;
use App\Enums\Relevance;
use App\Enums\RequestType;
use App\Models\ContactRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    protected $model = ContactRequest::class;

    /**
     * Indicative project types used to make qualified fixtures readable.
     *
     * @var list<string>
     */
    private const PROJECT_TYPES = [
        'rénovation de salle de bain',
        'pose de carrelage au sol',
        'peinture séjour et couloir',
        'remplacement de chaudière',
        'création de cloison placo',
        'pose de parquet flottant',
    ];

    /**
     * Realistic, priceable quote requests (nature of the work + quantities).
     *
     * @var list<string>
     */
    private const DETAILED_DESCRIPTIONS = [
        'Bonjour, je souhaite faire repeindre mon séjour et le couloir, environ 45 m² de murs et 18 m² de plafond. Les murs sont en bon état, juste un rebouchage de quelques trous. Pouvez-vous me faire un devis ?',
        'Remplacement de ma vieille chaudière gaz par une chaudière à condensation. Maison de 110 m² avec 7 radiateurs. Je suis disponible en semaine après 17 h.',
        'Pose de carrelage dans une salle de bain : 6 m² au sol et 12 m² de faïence sur les murs. Le carrelage est déjà acheté, il faut aussi retirer l\'ancien.',
        'J\'aimerais poser du parquet flottant dans deux chambres, environ 28 m² au total. Sol actuel en moquette à enlever.',
        'Création d\'une cloison placo de 9 m² pour isoler un bureau dans le salon, avec une porte coulissante.',
        'Mise aux normes du tableau électrique d\'un appartement de 65 m² et ajout de 4 prises dans le séjour.',
        'Installation d\'un chauffe-eau thermodynamique de 200 L en remplacement d\'un ballon électrique, dans le garage.',
        'Réfection complète d\'une salle de bain de 5 m² : dépose de la baignoire, pose d\'une douche à l\'italienne, faïence et plomberie.',
    ];

    /**
     * Vague requests missing the quantities needed to quote — representative of a
     * prospect who acknowledged they cannot provide the details.
     *
     * @var list<string>
     */
    private const VAGUE_DESCRIPTIONS = [
        'Bonjour, j\'ai des travaux à faire chez moi, pouvez-vous me rappeler pour en discuter ?',
        'Je voudrais un devis pour rénover ma maison, merci de me contacter.',
        'J\'ai un souci dans ma salle de bain, c\'est assez urgent.',
        'Pouvez-vous passer sur place pour voir ce qu\'il y a à faire ?',
        'Besoin d\'un artisan rapidement, je ne sais pas trop par où commencer.',
    ];

    /**
     * Off-topic requests: spam, another trade, or another area.
     *
     * @var list<string>
     */
    private const IRRELEVANT_DESCRIPTIONS = [
        'Bonjour, je propose des prestations de référencement SEO pour booster votre site internet. Contactez-moi pour une offre.',
        'Augmentez votre visibilité sur Google dès maintenant, offre spéciale réservée aux artisans ce mois-ci !',
        'Je cherche quelqu\'un pour réparer ma voiture, le moteur fait un bruit bizarre.',
        'Recherche un plombier pour une fuite urgente sur Marseille, dans le 13008.',
        'Besoin d\'un électricien pour les locaux de mon entreprise à Lille, intervention récurrente.',
    ];

    /**
     * A raw, not-yet-qualified request (qualification columns null). Apply a state
     * below to simulate the outcome of the async qualification.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'request_type' => fake()->randomElement(RequestType::cases()),
            'deadline' => fake()->randomElement(Deadline::cases()),
            'postal_code' => fake()->optional()->numerify('#####'),
            'description' => fake()->randomElement(self::DETAILED_DESCRIPTIONS),
        ];
    }

    /**
     * Explicitly not-yet-qualified (qualified_at null) — the dashboard "en attente
     * de qualification" state. Same as the bare definition, named for tests.
     */
    public function unqualified(): static
    {
        return $this->state(fn (): array => [
            'relevance' => null,
            'project_type' => null,
            'summary' => null,
            'estimated_amount_min' => null,
            'estimated_amount_max' => null,
            'lead_quality' => null,
            'priority' => null,
            'qualified_at' => null,
            'missing_info_acknowledged' => false,
        ]);
    }

    /**
     * A relevant, fully qualified request with a € estimate. Lead quality and
     * priority are derived from the row (mirroring QualifyContactRequest) so the
     * fixtures stay coherent with the production logic.
     */
    public function qualified(): static
    {
        return $this->state(function (array $attributes): array {
            $min = fake()->numberBetween(2, 55) * 100;
            $max = $min + fake()->numberBetween(2, 40) * 100;
            $leadQuality = $this->leadQuality($attributes);

            return [
                'relevance' => Relevance::Relevant,
                'project_type' => fake()->randomElement(self::PROJECT_TYPES),
                'summary' => fake()->sentence(),
                'estimated_amount_min' => $min,
                'estimated_amount_max' => $max,
                'lead_quality' => $leadQuality,
                'priority' => $this->derivePriority($max, $this->deadline($attributes), $leadQuality),
                'qualified_at' => fake()->dateTimeBetween('-10 days', 'now'),
            ];
        });
    }

    /**
     * A qualified-but-irrelevant request (spam / out-of-area / out-of-trade):
     * relevance floors the priority to low and the € estimate stays null.
     */
    public function irrelevant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'description' => fake()->randomElement(self::IRRELEVANT_DESCRIPTIONS),
            'relevance' => fake()->randomElement([Relevance::Spam, Relevance::OutOfArea, Relevance::OutOfTrade]),
            'project_type' => null,
            'summary' => fake()->sentence(),
            'estimated_amount_min' => null,
            'estimated_amount_max' => null,
            'lead_quality' => $this->leadQuality($attributes),
            'priority' => Priority::Low,
            'qualified_at' => fake()->dateTimeBetween('-10 days', 'now'),
        ]);
    }

    /**
     * The prospect acknowledged they could not provide the missing details: the
     * request passed the soft gate and is qualified, but with no € estimate (no
     * quantities to price).
     */
    public function missingInfoAcknowledged(): static
    {
        return $this->state(function (array $attributes): array {
            $leadQuality = $this->leadQuality($attributes);

            return [
                'description' => fake()->randomElement(self::VAGUE_DESCRIPTIONS),
                'missing_info_acknowledged' => true,
                'relevance' => Relevance::Relevant,
                'project_type' => fake()->randomElement(self::PROJECT_TYPES),
                'summary' => fake()->sentence(),
                'estimated_amount_min' => null,
                'estimated_amount_max' => null,
                'lead_quality' => $leadQuality,
                'priority' => $this->derivePriority(null, $this->deadline($attributes), $leadQuality),
                'qualified_at' => fake()->dateTimeBetween('-5 days', 'now'),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function leadQuality(array $attributes): LeadQuality
    {
        return ($attributes['phone'] ?? null) !== null && ($attributes['postal_code'] ?? null) !== null
            ? LeadQuality::Complete
            : LeadQuality::Incomplete;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deadline(array $attributes): Deadline
    {
        $deadline = $attributes['deadline'] ?? Deadline::NotUrgent;

        return $deadline instanceof Deadline ? $deadline : Deadline::from($deadline);
    }

    /**
     * Mirrors QualifyContactRequest::priority() with the configured estimate tiers,
     * so seeded priorities match what the real qualifier would produce.
     */
    private function derivePriority(?int $estimateMax, Deadline $deadline, LeadQuality $leadQuality): Priority
    {
        $high = (int) config('qualification.priority.estimation_high');
        $medium = (int) config('qualification.priority.estimation_medium');

        $score = match (true) {
            $estimateMax === null => 0,
            $estimateMax >= $high => 4,
            $estimateMax >= $medium => 2,
            default => 0,
        } + match ($deadline) {
            Deadline::Immediate => 2,
            Deadline::WithinOneMonth => 1,
            default => 0,
        } + ($leadQuality === LeadQuality::Complete ? 1 : 0);

        return match (true) {
            $score >= 5 => Priority::High,
            $score >= 2 => Priority::Medium,
            default => Priority::Low,
        };
    }
}
