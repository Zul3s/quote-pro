<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Output of the synchronous submission gate (Qualifier::assess). NOT an App\Data:
 * it carries no form validation and is never built from a Request — it is a plain
 * readonly value object returned by the Qualifier contract.
 *
 * When $sufficient is false, $message lists precisely what the prospect must add
 * (e.g. "précisez la surface en m² et la nature des travaux"); it is surfaced as
 * the validation error on the contact form.
 */
final readonly class SufficiencyResult
{
    public function __construct(
        public bool $sufficient,
        public ?string $message = null,
    ) {}
}
