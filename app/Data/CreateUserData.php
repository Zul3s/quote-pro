<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * Input contract for the CreateUser Action. Carries form validation only.
 *
 * Built exclusively through the named constructors below (each validates via
 * `validateAndCreate`) — direct `new CreateUserData(...)` is forbidden and
 * enforced by tests/Unit/ArchDataConstructionTest.php.
 */
final class CreateUserData extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:180'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return self::validateAndCreate($request->all());
    }

    public static function fromValues(string $email, ?string $firstName = null, ?string $lastName = null): self
    {
        return self::validateAndCreate(compact('email', 'firstName', 'lastName'));
    }
}
