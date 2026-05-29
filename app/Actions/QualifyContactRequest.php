<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\QualificationResult;
use App\Contracts\Qualifier;
use App\Enums\Deadline;
use App\Enums\LeadQuality;
use App\Enums\Priority;
use App\Enums\Relevance;
use App\Models\ArtisanProfile;
use App\Models\ContactRequest;

/**
 * Async qualification use case (run from the queued QualifySubmittedContactRequest
 * listener). Asks the Qualifier for the semantic labels, derives the mechanical
 * ones (lead quality, priority) deterministically, and persists everything plus
 * qualified_at.
 *
 * Lets the Qualifier exception propagate: a failed LLM call leaves the row
 * unqualified (no update runs) and the queue retries — the lead is never lost.
 */
final readonly class QualifyContactRequest
{
    public function handle(ContactRequest $contactRequest): ContactRequest
    {
        $profile = ArtisanProfile::first();

        if ($profile === null) {
            return $contactRequest;
        }

        $result = app(Qualifier::class)->qualify($contactRequest, $profile);

        $leadQuality = $this->leadQuality($contactRequest);

        $contactRequest->update([
            'relevance' => $result->relevance,
            'project_type' => $result->projectType,
            'summary' => $result->summary,
            'estimated_amount_min' => $result->estimatedAmountMin,
            'estimated_amount_max' => $result->estimatedAmountMax,
            'lead_quality' => $leadQuality,
            'priority' => $this->priority($result, $contactRequest->deadline, $leadQuality),
            'qualified_at' => now(),
        ]);

        return $contactRequest;
    }

    private function leadQuality(ContactRequest $request): LeadQuality
    {
        return $request->phone !== null && $request->postal_code !== null
            ? LeadQuality::Complete
            : LeadQuality::Incomplete;
    }

    /**
     * Deterministic priority. Relevance is a hard floor (spam / out-of-scope →
     * low); otherwise the € estimate dominates, refined by deadline and lead
     * completeness. Estimate tiers are configurable (config/qualification.php).
     */
    private function priority(QualificationResult $result, Deadline $deadline, LeadQuality $leadQuality): Priority
    {
        if (in_array($result->relevance, [Relevance::Spam, Relevance::OutOfArea, Relevance::OutOfTrade], true)) {
            return Priority::Low;
        }

        $score = $this->estimationWeight($result->estimatedAmountMax)
            + $this->deadlineWeight($deadline)
            + ($leadQuality === LeadQuality::Complete ? 1 : 0);

        return match (true) {
            $score >= 5 => Priority::High,
            $score >= 2 => Priority::Medium,
            default => Priority::Low,
        };
    }

    private function estimationWeight(?int $amount): int
    {
        if ($amount === null) {
            return 0;
        }

        $high = (int) config('qualification.priority.estimation_high');
        $medium = (int) config('qualification.priority.estimation_medium');

        return match (true) {
            $amount >= $high => 4,
            $amount >= $medium => 2,
            default => 0,
        };
    }

    private function deadlineWeight(Deadline $deadline): int
    {
        return match ($deadline) {
            Deadline::Immediate => 2,
            Deadline::WithinOneMonth => 1,
            default => 0,
        };
    }
}
