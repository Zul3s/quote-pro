<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\ArtisanProfile;
use App\Models\ContactRequest;

/**
 * Contract for the hybrid qualification engine. The production binding is
 * App\Services\OllamaQualifier (local LLM); tests bind Tests\Fakes\FakeQualifier
 * so no test ever hits the network. Bound explicitly in AppServiceProvider.
 *
 * Its output value objects (SufficiencyResult, QualificationResult) live in this
 * same App\Contracts namespace: they are part of the contract's signature, not
 * validated input DTOs (which belong to App\Data).
 */
interface Qualifier
{
    /**
     * Synchronous submission gate. Derives from the artisan profile (professions
     * + free-text services) the information needed to quote this request, then
     * judges whether the free-text description carries enough of it — both the
     * "what" (nature of the work) and the quantifiable quantities (notably
     * surface area). When insufficient, SufficiencyResult::$message lists
     * precisely, in prose, what is missing for this trade.
     *
     * @throws \Throwable when the underlying LLM is unavailable — callers decide
     *                    whether to fail open (the submission gate) or retry (the job).
     */
    public function assess(string $description, ArtisanProfile $profile): SufficiencyResult;

    /**
     * Asynchronous full qualification. Produces the semantic labels (relevance,
     * project type, summary, € estimate) by crossing the request with the
     * artisan's professions, area and free-text rate card.
     *
     * @throws \Throwable when the underlying LLM is unavailable.
     */
    public function qualify(ContactRequest $request, ArtisanProfile $profile): QualificationResult;
}
