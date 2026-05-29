<?php

namespace Database\Factories;

use App\Enums\Deadline;
use App\Enums\RequestType;
use App\Models\ContactRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    protected $model = ContactRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'request_type' => fake()->randomElement(RequestType::cases()),
            'deadline' => fake()->randomElement(Deadline::cases()),
            'postal_code' => fake()->optional()->numerify('#####'),
            'description' => fake()->paragraph(),
        ];
    }
}
