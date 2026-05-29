<?php

declare(strict_types=1);

use App\Actions\ListContactRequests;
use App\Data\ListContactRequestsData;
use App\Enums\Deadline;
use App\Enums\RequestType;
use App\Models\ContactRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| ListContactRequests Action
|--------------------------------------------------------------------------
*/

it('lists contact requests newest first', function () {
    $old = ContactRequest::factory()->create(['created_at' => now()->subDays(2)]);
    $mid = ContactRequest::factory()->create(['created_at' => now()->subDay()]);
    $new = ContactRequest::factory()->create(['created_at' => now()]);

    $result = (new ListContactRequests)->handle(ListContactRequestsData::fromValues());

    expect(collect($result->items())->pluck('uuid')->all())
        ->toBe([$new->uuid, $mid->uuid, $old->uuid]);
});

it('filters by request type', function () {
    ContactRequest::factory()->count(2)->create(['request_type' => RequestType::Quote]);
    ContactRequest::factory()->count(3)->create(['request_type' => RequestType::Information]);

    $result = (new ListContactRequests)->handle(ListContactRequestsData::fromValues(type: RequestType::Quote));

    expect($result->total())->toBe(2)
        ->and(collect($result->items())->every(fn (ContactRequest $r) => $r->request_type === RequestType::Quote))->toBeTrue();
});

it('filters by deadline', function () {
    ContactRequest::factory()->count(2)->create(['deadline' => Deadline::Immediate]);
    ContactRequest::factory()->count(3)->create(['deadline' => Deadline::NotUrgent]);

    $result = (new ListContactRequests)->handle(ListContactRequestsData::fromValues(deadline: Deadline::Immediate));

    expect($result->total())->toBe(2)
        ->and(collect($result->items())->every(fn (ContactRequest $r) => $r->deadline === Deadline::Immediate))->toBeTrue();
});

it('applies type and deadline filters together with AND logic', function () {
    $match = ContactRequest::factory()->create(['request_type' => RequestType::Quote, 'deadline' => Deadline::Immediate]);
    ContactRequest::factory()->create(['request_type' => RequestType::Quote, 'deadline' => Deadline::NotUrgent]);
    ContactRequest::factory()->create(['request_type' => RequestType::Information, 'deadline' => Deadline::Immediate]);

    $result = (new ListContactRequests)->handle(ListContactRequestsData::fromValues(
        type: RequestType::Quote,
        deadline: Deadline::Immediate,
    ));

    expect($result->total())->toBe(1)
        ->and($result->first()->uuid)->toBe($match->uuid);
});

it('paginates at 25 per page', function () {
    ContactRequest::factory()->count(30)->create();

    $result = (new ListContactRequests)->handle(ListContactRequestsData::fromValues());

    expect($result->perPage())->toBe(25)
        ->and($result->total())->toBe(30)
        ->and($result->count())->toBe(25);
});

it('rejects an out-of-enum filter at the DTO boundary', function (callable $build) {
    expect($build)->toThrow(ValidationException::class);
})->with([
    'invalid type' => [fn () => ListContactRequestsData::fromRequest(Request::create('/dashboard', 'GET', ['type' => 'not-a-type']))],
    'invalid deadline' => [fn () => ListContactRequestsData::fromRequest(Request::create('/dashboard', 'GET', ['deadline' => 'someday']))],
]);
