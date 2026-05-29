<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\QualificationResult;
use App\Contracts\Qualifier;
use App\Contracts\SufficiencyResult;
use App\Enums\Relevance;
use App\Models\ArtisanProfile;
use App\Models\ContactRequest;
use RuntimeException;

/**
 * In-memory Qualifier double for tests — no network. Bind it over the contract
 * in a test (`$this->app->instance(Qualifier::class, FakeQualifier::sufficient())`).
 *
 * Three modes:
 *  - sufficient()/insufficient(...) drive the synchronous gate (assess);
 *  - withResult(...) fixes the async qualification output (qualify);
 *  - throwing() makes both assess() and qualify() raise, to exercise the
 *    fail-open gate and the queued-job retry path.
 */
final class FakeQualifier implements Qualifier
{
    private bool $shouldThrow = false;

    public function __construct(
        private bool $sufficient = true,
        private ?string $message = null,
        private ?QualificationResult $result = null,
    ) {}

    public static function sufficient(): self
    {
        return new self(sufficient: true);
    }

    public static function insufficient(string $message): self
    {
        return new self(sufficient: false, message: $message);
    }

    public static function throwing(): self
    {
        $fake = new self;
        $fake->shouldThrow = true;

        return $fake;
    }

    public function withResult(QualificationResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function assess(string $description, ArtisanProfile $profile): SufficiencyResult
    {
        if ($this->shouldThrow) {
            throw new RuntimeException('FakeQualifier: assess failure simulated.');
        }

        return new SufficiencyResult($this->sufficient, $this->message);
    }

    public function qualify(ContactRequest $request, ArtisanProfile $profile): QualificationResult
    {
        if ($this->shouldThrow) {
            throw new RuntimeException('FakeQualifier: qualify failure simulated.');
        }

        return $this->result ?? new QualificationResult(
            relevance: Relevance::Relevant,
            projectType: 'travaux',
            summary: 'Demande de devis.',
            estimatedAmountMin: 500,
            estimatedAmountMax: 1500,
        );
    }
}
