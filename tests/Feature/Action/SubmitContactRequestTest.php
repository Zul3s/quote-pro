<?php

declare(strict_types=1);

use App\Actions\SubmitContactRequest;
use App\Contracts\Qualifier;
use App\Data\SubmitContactRequestData;
use App\Enums\Deadline;
use App\Enums\RequestType;
use App\Events\ContactRequestSubmitted;
use App\Models\ArtisanProfile;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeQualifier;

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

/*
|--------------------------------------------------------------------------
| Soft sufficiency gate (DescriptionIsSufficient rule)
|--------------------------------------------------------------------------
*/

it('rejects an insufficient description and persists nothing', function () {
    Event::fake([ContactRequestSubmitted::class]);
    ArtisanProfile::factory()->create();
    $this->app->instance(Qualifier::class, FakeQualifier::insufficient('Précisez la surface en m² et la nature des travaux.'));

    expect(fn () => (new SubmitContactRequest)->handle(SubmitContactRequestData::fromValues(
        name: 'Marie Dupont',
        email: 'marie.dupont@example.com',
        requestType: RequestType::Quote,
        deadline: Deadline::Immediate,
        description: 'Des travaux chez moi.',
    )))->toThrow(ValidationException::class, 'Précisez la surface en m² et la nature des travaux.');

    $this->assertDatabaseCount('contact_requests', 0);
    Event::assertNotDispatched(ContactRequestSubmitted::class);
});

it('skips the gate and persists when missing info is acknowledged', function () {
    Event::fake([ContactRequestSubmitted::class]);
    ArtisanProfile::factory()->create();
    // A throwing qualifier proves the gate is not invoked when acknowledged.
    $this->app->instance(Qualifier::class, FakeQualifier::throwing());

    $contactRequest = (new SubmitContactRequest)->handle(SubmitContactRequestData::fromValues(
        name: 'Marie Dupont',
        email: 'marie.dupont@example.com',
        requestType: RequestType::Quote,
        deadline: Deadline::Immediate,
        description: 'Des travaux chez moi.',
        acknowledgeMissingInfo: true,
    ));

    expect($contactRequest->missing_info_acknowledged)->toBeTrue();
    $this->assertDatabaseHas('contact_requests', [
        'email' => 'marie.dupont@example.com',
        'missing_info_acknowledged' => true,
    ]);
    Event::assertDispatched(ContactRequestSubmitted::class);
});

it('persists when the description is judged sufficient', function () {
    Event::fake([ContactRequestSubmitted::class]);
    ArtisanProfile::factory()->create();
    $this->app->instance(Qualifier::class, FakeQualifier::sufficient());

    $contactRequest = (new SubmitContactRequest)->handle(SubmitContactRequestData::fromValues(
        name: 'Marie Dupont',
        email: 'marie.dupont@example.com',
        requestType: RequestType::Quote,
        deadline: Deadline::Immediate,
        description: 'Repeindre 40 m² de murs et 18 m² de plafond dans le séjour.',
    ));

    expect($contactRequest->missing_info_acknowledged)->toBeFalse();
    $this->assertDatabaseHas('contact_requests', ['email' => 'marie.dupont@example.com']);
    Event::assertDispatched(ContactRequestSubmitted::class);
});

it('lets the submission through when the gate qualifier fails (fail-open)', function () {
    Event::fake([ContactRequestSubmitted::class]);
    ArtisanProfile::factory()->create();
    $this->app->instance(Qualifier::class, FakeQualifier::throwing());

    $contactRequest = (new SubmitContactRequest)->handle(SubmitContactRequestData::fromValues(
        name: 'Marie Dupont',
        email: 'marie.dupont@example.com',
        requestType: RequestType::Quote,
        deadline: Deadline::Immediate,
        description: 'Des travaux chez moi.',
    ));

    expect($contactRequest->missing_info_acknowledged)->toBeFalse();
    $this->assertDatabaseHas('contact_requests', ['email' => 'marie.dupont@example.com']);
    Event::assertDispatched(ContactRequestSubmitted::class);
});
