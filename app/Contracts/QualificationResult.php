<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\Relevance;

/**
 * Output of the asynchronous full qualification (Qualifier::qualify). NOT an
 * App\Data: it is a plain readonly value object carrying only the LLM-produced
 * (semantic) labels. The mechanical labels — lead_quality, priority — are derived
 * deterministically in QualifyContactRequest, not here.
 *
 * Estimated amounts are nullable: a "missing info acknowledged" request may have
 * no quantities to price, which is a legitimate result, not a failure.
 */
final readonly class QualificationResult
{
    public function __construct(
        public Relevance $relevance,
        public string $projectType,
        public string $summary,
        public ?int $estimatedAmountMin = null,
        public ?int $estimatedAmountMax = null,
    ) {}
}
