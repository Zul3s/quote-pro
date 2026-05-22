<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use Spatie\LaravelData\Data;

/**
 * Base class for Use Case output DTOs.
 *
 * Use this ONLY when a Use Case needs to return a combination of entities,
 * or when its payload must be serialised to Inertia/TS. For simpler cases,
 * return the Domain entity (or array of EntityInterface) directly.
 */
abstract class AbstractResponse extends Data {}
