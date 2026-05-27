<?php

declare(strict_types=1);

use App\Application\UseCase\SubmitContactRequest\Request;
use App\Application\UseCase\SubmitContactRequest\UseCase;
use App\Domain\Entity\ContactRequestInterface;
use App\Domain\Event\ContactRequest\ContactRequestSubmitted;
use App\Domain\Exception\ValidationsException;
use App\Domain\Model\Deadline;
use App\Domain\Model\RequestType;
use Illuminate\Support\Facades\Event;

/**
 * @return array<string, mixed>
 */
function validContactRequestPayload(): array
{
    return [
        'name' => 'Marie Dupont',
        'email' => 'marie.dupont@example.com',
        'requestType' => 'quote',
        'deadline' => 'within_one_month',
        'description' => 'Besoin d\'un devis pour la rénovation de ma salle de bain.',
        'phone' => '+33 6 12 34 56 78',
        'postalCode' => '75011',
    ];
}

/*
|--------------------------------------------------------------------------
| SubmitContactRequest Use Case
|--------------------------------------------------------------------------
| Tests of the Application Use Case only. HTTP/transport concerns are
| covered separately in tests/Functional/Controller/ContactRequest/.
*/

it('persists a contact request and dispatches the submitted event', function () {
    Event::fake([ContactRequestSubmitted::class]);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    $contactRequest = $useCase->execute(new Request(
        name: 'Marie Dupont',
        email: 'marie.dupont@example.com',
        requestType: RequestType::Quote,
        deadline: Deadline::WithinOneMonth,
        description: 'Besoin d\'un devis pour la rénovation de ma salle de bain.',
        phone: '+33 6 12 34 56 78',
        postalCode: '75011',
    ));

    expect($contactRequest)->toBeInstanceOf(ContactRequestInterface::class);
    expect($contactRequest->getUuid()->toString())->toBeUuid();
    expect($contactRequest->getName())->toBe('Marie Dupont');
    expect($contactRequest->getEmail())->toBe('marie.dupont@example.com');
    expect($contactRequest->getRequestType())->toBe(RequestType::Quote);
    expect($contactRequest->getDeadline())->toBe(Deadline::WithinOneMonth);
    expect($contactRequest->getPhone())->toBe('+33 6 12 34 56 78');
    expect($contactRequest->getPostalCode())->toBe('75011');

    $this->assertDatabaseHas('contact_requests', [
        'name' => 'Marie Dupont',
        'email' => 'marie.dupont@example.com',
        'request_type' => 'quote',
        'deadline' => 'within_one_month',
        'phone' => '+33 6 12 34 56 78',
        'postal_code' => '75011',
    ]);

    Event::assertDispatched(ContactRequestSubmitted::class, function (ContactRequestSubmitted $event) use ($contactRequest) {
        return $event->aggregateId()->equals($contactRequest->getUuid())
            && $event->email === 'marie.dupont@example.com'
            && $event->requestType === RequestType::Quote
            && $event->deadline === Deadline::WithinOneMonth;
    });
});

it('stores null phone and postal code when omitted', function () {
    Event::fake([ContactRequestSubmitted::class]);

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    $contactRequest = $useCase->execute(new Request(
        name: 'Jean Martin',
        email: 'jean.martin@example.com',
        requestType: RequestType::Urgent,
        deadline: Deadline::Immediate,
        description: 'Fuite d\'eau urgente, intervention immédiate requise.',
    ));

    expect($contactRequest->getPhone())->toBeNull();
    expect($contactRequest->getPostalCode())->toBeNull();

    $this->assertDatabaseHas('contact_requests', [
        'email' => 'jean.martin@example.com',
        'request_type' => 'urgent',
        'deadline' => 'immediate',
        'phone' => null,
        'postal_code' => null,
    ]);

    Event::assertDispatchedTimes(ContactRequestSubmitted::class, 1);
});

it('rejects a malformed Request via execute and persists nothing', function (array $override) {
    Event::fake([ContactRequestSubmitted::class]);

    $payload = [...validContactRequestPayload(), ...$override];
    $request = Request::from($payload); // hydrate without validating

    /** @var UseCase $useCase */
    $useCase = app(UseCase::class);

    expect(fn () => $useCase->execute($request))
        ->toThrow(ValidationsException::class);

    $this->assertDatabaseCount('contact_requests', 0);
    Event::assertNotDispatched(ContactRequestSubmitted::class);
})->with([
    'invalid email format' => [['email' => 'not-an-email']],
    'name exceeds max length' => [['name' => str_repeat('a', 151)]],
    'email exceeds max length' => [['email' => str_repeat('a', 170).'@example.com']],
    'description exceeds max length' => [['description' => str_repeat('x', 5001)]],
    'phone exceeds max length' => [['phone' => str_repeat('1', 41)]],
    'postal code exceeds max length' => [['postalCode' => str_repeat('1', 21)]],
]);
