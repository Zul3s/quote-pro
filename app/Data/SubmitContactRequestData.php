<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Deadline;
use App\Enums\RequestType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Input contract for the SubmitContactRequest Action. Carries form validation only.
 *
 * Built exclusively through the named constructors below — direct
 * `new SubmitContactRequestData(...)` is forbidden (see ArchDataConstructionTest).
 */
final class SubmitContactRequestData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly RequestType $requestType,
        public readonly Deadline $deadline,
        public readonly string $description,
        public readonly ?string $phone = null,
        public readonly ?string $postalCode = null,
        public readonly bool $acknowledgeMissingInfo = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180'],
            'requestType' => ['required', Rule::enum(RequestType::class)],
            'deadline' => ['required', Rule::enum(Deadline::class)],
            'description' => ['required', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'acknowledgeMissingInfo' => ['nullable', 'boolean'],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return self::validateAndCreate($request->all());
    }

    public static function fromValues(
        string $name,
        string $email,
        RequestType $requestType,
        Deadline $deadline,
        string $description,
        ?string $phone = null,
        ?string $postalCode = null,
        bool $acknowledgeMissingInfo = false,
    ): self {
        return self::validateAndCreate([
            'name' => $name,
            'email' => $email,
            'requestType' => $requestType->value,
            'deadline' => $deadline->value,
            'description' => $description,
            'phone' => $phone,
            'postalCode' => $postalCode,
            'acknowledgeMissingInfo' => $acknowledgeMissingInfo,
        ]);
    }
}
