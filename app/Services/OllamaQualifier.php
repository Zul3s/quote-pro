<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\QualificationResult;
use App\Contracts\Qualifier;
use App\Contracts\SufficiencyResult;
use App\Enums\Relevance;
use App\Models\ArtisanProfile;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

/**
 * Local-LLM adapter for the Qualifier contract, talking to Ollama's
 * /api/generate endpoint with JSON-formatted output. Configured under
 * config('services.ollama'). Throws on any failure (HTTP error, unparseable
 * payload, out-of-domain enum) — callers decide the fallback policy.
 */
final readonly class OllamaQualifier implements Qualifier
{
    public function assess(string $description, ArtisanProfile $profile): SufficiencyResult
    {
        $payload = $this->generate(<<<PROMPT
            Tu assistes un artisan ({$this->professions($profile)}). Voici son barème (texte libre) : {$profile->services}.
            Un prospect a écrit la demande ci-dessous. À partir du métier et du barème de l'artisan,
            déduis les informations nécessaires pour ÉTABLIR UN DEVIS, puis juge si la demande les contient.
            Il faut a minima : (1) le « quoi » — la nature des travaux est identifiable — et (2) les quantités
            chiffrables pertinentes pour ce métier (notamment la surface en m², et selon le cas le nombre de
            pièces ou d'éléments). Si l'un manque, elle est insuffisante.

            Réponds en JSON strict : {"sufficient": bool, "message": string}. Quand insuffisant,
            "message" liste précisément, en français et en une phrase, ce que le prospect doit
            ajouter (ex. « précisez la surface en m² et la nature des travaux »). Quand suffisant,
            "message" vaut "".

            Demande :
            {$description}
            PROMPT);

        return new SufficiencyResult(
            sufficient: (bool) ($payload['sufficient'] ?? false),
            message: ($payload['message'] ?? '') !== '' ? (string) $payload['message'] : null,
        );
    }

    public function qualify(ContactRequest $request, ArtisanProfile $profile): QualificationResult
    {
        $payload = $this->generate(<<<PROMPT
            Tu qualifies une demande entrante pour un artisan.
            Métiers de l'artisan : {$this->professions($profile)}.
            Zone (code postal) : {$profile->postal_code}.
            Barème tarifaire (texte libre) : {$profile->services}.

            Demande du prospect :
            - Type déclaré : {$request->request_type->value}
            - Description : {$request->description}
            - Code postal du prospect : {$request->postal_code}

            Réponds en JSON strict avec EXACTEMENT ces clés :
            {
              "relevance": "relevant" | "out_of_area" | "out_of_trade" | "spam",
              "project_type": string,        // nature des travaux, en quelques mots
              "summary": string,             // résumé de la demande en une phrase
              "estimated_amount_min": number|null,  // estimation basse en euros, croisée avec le barème
              "estimated_amount_max": number|null   // estimation haute en euros (null si non chiffrable)
            }
            PROMPT);

        return new QualificationResult(
            relevance: Relevance::from((string) ($payload['relevance'] ?? '')),
            projectType: (string) ($payload['project_type'] ?? ''),
            summary: (string) ($payload['summary'] ?? ''),
            estimatedAmountMin: $this->nullableInt($payload['estimated_amount_min'] ?? null),
            estimatedAmountMax: $this->nullableInt($payload['estimated_amount_max'] ?? null),
        );
    }

    /**
     * POST a prompt to Ollama and decode the embedded JSON response.
     *
     * @return array<string, mixed>
     */
    private function generate(string $prompt): array
    {
        $response = Http::baseUrl((string) config('services.ollama.base_url'))
            ->timeout(30)
            ->post('/api/generate', [
                'model' => (string) config('services.ollama.model'),
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ])
            ->throw();

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $response->json('response'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Ollama returned an unparseable JSON payload.', previous: $e);
        }

        return $decoded;
    }

    private function professions(ArtisanProfile $profile): string
    {
        /** @var list<string> $professions */
        $professions = $profile->professions ?? [];

        return implode(', ', $professions);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
