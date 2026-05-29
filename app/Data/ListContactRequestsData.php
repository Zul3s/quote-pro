<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Deadline;
use App\Enums\RequestType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Input contract for the ListContactRequests read Action. Carries the optional
 * server-side filters (type, deadline) with form validation only — both are
 * nullable and constrained to their enum domain.
 *
 * Built exclusively through the named constructors below — direct
 * `new ListContactRequestsData(...)` is forbidden (see ArchDataConstructionTest).
 */
final class ListContactRequestsData extends Data
{
    public function __construct(
        public readonly ?RequestType $type = null,
        public readonly ?Deadline $deadline = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(RequestType::class)],
            'deadline' => ['nullable', Rule::enum(Deadline::class)],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return self::validateAndCreate($request->only('type', 'deadline'));
    }

    public static function fromValues(?RequestType $type = null, ?Deadline $deadline = null): self
    {
        return self::validateAndCreate([
            'type' => $type?->value,
            'deadline' => $deadline?->value,
        ]);
    }
}
