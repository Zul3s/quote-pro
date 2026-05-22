<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use Spatie\LaravelData\Data;

/**
 * Base class for all Use Case input DTOs.
 *
 * Decision: we extend Spatie\LaravelData\Data here (Application layer only),
 * never in Domain. Gives us validation-by-attributes, auto-hydration from
 * HTTP/JSON, and TypeScript generation. See docs/architecture.md §3.
 *
 * Every Use Case Request MUST extend this class — enforced by ArchTest.
 */
abstract class AbstractRequest extends Data {}
