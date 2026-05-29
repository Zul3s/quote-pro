<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * Input contract for the SaveArtisanProfile Action. Carries form validation only.
 *
 * Built exclusively through the named constructors below (each validates via
 * `validateAndCreate`) — direct `new SaveArtisanProfileData(...)` is forbidden and
 * enforced by tests/Unit/ArchDataConstructionTest.php.
 */
final class SaveArtisanProfileData extends Data
{
    /**
     * @param  list<string>  $professions
     */
    public function __construct(
        public readonly string $postalCode,
        public readonly array $professions,
        public readonly ?string $services = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'postalCode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'professions' => ['required', 'array', 'min:1'],
            'professions.*' => ['string', 'max:100'],
            'services' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public static function fromRequest(Request $request): self
    {
        return self::validateAndCreate($request->all());
    }

    /**
     * @param  list<string>  $professions
     */
    public static function fromValues(string $postalCode, array $professions, ?string $services = null): self
    {
        return self::validateAndCreate(compact('postalCode', 'professions', 'services'));
    }
}
