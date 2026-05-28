<?php

declare(strict_types=1);

use App\Actions\SubmitContactRequest;
use App\Data\SubmitContactRequestData;
use App\Enums\Deadline;
use App\Enums\RequestType;
use App\Events\ContactRequestSubmitted;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| SubmitContactRequest Action
|--------------------------------------------------------------------------
*/

it('persists a contact request and dispatches the submitted event', function () {
    Event::fake([ContactRequestSubmitted::class]);

    $contactRequest = (new SubmitContactRequest)->handle(SubmitContactRequestData::fromValues(
        name: 'Marie Dupont',
        email: 'marie.dupont@example.com',
        requestType: RequestType::Quote,
        deadline: Deadline::WithinOneMonth,
        description: 'Besoin d\'un devis pour la rénovation de ma salle de bain.',
        phone: '+33 6 12 34 56 78',
        postalCode: '75011',
    ));

    expect($contactRequest->uuid)->toBeUuid()
        ->and($contactRequest->request_type)->toBe(RequestType::Quote)
        ->and($contactRequest->deadline)->toBe(Deadline::WithinOneMonth);

    $this->assertDatabaseHas('contact_requests', [
        'name' => 'Marie Dupont',
        'email' => 'marie.dupont@example.com',
        'request_type' => 'quote',
        'deadline' => 'within_one_month',
        'phone' => '+33 6 12 34 56 78',
        'postal_code' => '75011',
    ]);

    Event::assertDispatched(
        ContactRequestSubmitted::class,
        fn (ContactRequestSubmitted $event) => $event->contactRequest->is($contactRequest),
    );
});

it('stores null phone and postal code when omitted', function () {
    Event::fake([ContactRequestSubmitted::class]);

    $contactRequest = (new SubmitContactRequest)->handle(SubmitContactRequestData::fromValues(
        name: 'Jean Martin',
        email: 'jean.martin@example.com',
        requestType: RequestType::Urgent,
        deadline: Deadline::Immediate,
        description: 'Fuite d\'eau urgente, intervention immédiate requise.',
    ));

    expect($contactRequest->phone)->toBeNull()
        ->and($contactRequest->postal_code)->toBeNull();

    $this->assertDatabaseHas('contact_requests', [
        'email' => 'jean.martin@example.com',
        'request_type' => 'urgent',
        'deadline' => 'immediate',
        'phone' => null,
        'postal_code' => null,
    ]);
});

it('rejects malformed input at the DTO boundary', function (callable $build) {
    expect($build)->toThrow(ValidationException::class);
})->with([
    'invalid email' => [fn () => SubmitContactRequestData::fromValues('Marie', 'not-an-email', RequestType::Quote, Deadline::Immediate, 'desc')],
    'name too long' => [fn () => SubmitContactRequestData::fromValues(str_repeat('a', 151), 'a@b.com', RequestType::Quote, Deadline::Immediate, 'desc')],
    'description too long' => [fn () => SubmitContactRequestData::fromValues('Marie', 'a@b.com', RequestType::Quote, Deadline::Immediate, str_repeat('x', 5001))],
]);
