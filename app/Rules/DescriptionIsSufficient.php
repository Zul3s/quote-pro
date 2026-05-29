<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\Qualifier;
use App\Models\ArtisanProfile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Business rule: the description must carry enough for the artisan to quote.
 * The Qualifier (LLM) judges sufficiency against the artisan profile; on failure
 * the $fail message is the LLM's prose listing what is missing, surfaced on the
 * contact form.
 *
 * Fail-open by design: if there is no profile to assess against, or the Qualifier
 * itself throws (Ollama down, timeout, bad JSON), the rule PASSES — a prospect is
 * never blocked by missing context or an infrastructure outage. The async
 * qualification (with its retries) catches up later.
 */
final readonly class DescriptionIsSufficient implements ValidationRule
{
    public function __construct(
        private Qualifier $qualifier,
        private ?ArtisanProfile $profile,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->profile === null) {
            return;
        }

        try {
            $result = $this->qualifier->assess((string) $value, $this->profile);
        } catch (Throwable $e) {
            Log::warning('Description sufficiency check failed; letting the submission through (fail-open).', [
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        if (! $result->sufficient) {
            $fail($result->message ?? "Merci de préciser votre besoin pour qu'un devis puisse être établi.");
        }
    }
}
