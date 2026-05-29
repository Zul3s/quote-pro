<?php

declare(strict_types=1);

use App\Contracts\QualificationResult;
use App\Contracts\Qualifier;
use App\Enums\Relevance;
use App\Events\ContactRequestSubmitted;
use App\Listeners\QualifySubmittedContactRequest;
use App\Models\ArtisanProfile;
use App\Models\ContactRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\Fakes\FakeQualifier;

/*
|--------------------------------------------------------------------------
| QualifySubmittedContactRequest Listener (on ContactRequestSubmitted)
|--------------------------------------------------------------------------
*/

it('is a queued listener', function () {
    expect(new QualifySubmittedContactRequest)->toBeInstanceOf(ShouldQueue::class);
});

it('qualifies the request when ContactRequestSubmitted fires', function () {
    ArtisanProfile::factory()->create();
    $request = ContactRequest::factory()->unqualified()->create();
    $this->app->instance(Qualifier::class, new FakeQualifier(result: new QualificationResult(
        relevance: Relevance::Relevant,
        projectType: 'peinture',
        summary: 'Repeindre le séjour.',
        estimatedAmountMin: 500,
        estimatedAmountMax: 900,
    )));

    ContactRequestSubmitted::dispatch($request);

    expect($request->fresh()->qualified_at)->not->toBeNull()
        ->and($request->fresh()->relevance)->toBe(Relevance::Relevant);
});
