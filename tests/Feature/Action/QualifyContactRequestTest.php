<?php

declare(strict_types=1);

use App\Actions\QualifyContactRequest;
use App\Contracts\QualificationResult;
use App\Contracts\Qualifier;
use App\Enums\Deadline;
use App\Enums\LeadQuality;
use App\Enums\Priority;
use App\Enums\Relevance;
use App\Models\ArtisanProfile;
use App\Models\ContactRequest;
use Tests\Fakes\FakeQualifier;

/*
|--------------------------------------------------------------------------
| QualifyContactRequest Action
|--------------------------------------------------------------------------
*/

it('persists the LLM labels and stamps qualified_at', function () {
    ArtisanProfile::factory()->create();
    $request = ContactRequest::factory()->unqualified()->create([
        'deadline' => Deadline::WithinOneMonth,
        'phone' => '0102030405',
        'postal_code' => '67000',
    ]);
    $this->app->instance(Qualifier::class, new FakeQualifier(result: new QualificationResult(
        relevance: Relevance::Relevant,
        projectType: 'pose de carrelage',
        summary: 'Pose de carrelage dans la cuisine.',
        estimatedAmountMin: 800,
        estimatedAmountMax: 1500,
    )));

    (new QualifyContactRequest)->handle($request);

    $this->assertDatabaseHas('contact_requests', [
        'uuid' => $request->uuid,
        'relevance' => 'relevant',
        'project_type' => 'pose de carrelage',
        'summary' => 'Pose de carrelage dans la cuisine.',
        'estimated_amount_min' => 800,
        'estimated_amount_max' => 1500,
        'lead_quality' => 'complete',
    ]);
    expect($request->fresh()->qualified_at)->not->toBeNull();
});

it('marks the lead incomplete when phone or postal code is missing', function () {
    ArtisanProfile::factory()->create();
    $request = ContactRequest::factory()->unqualified()->create([
        'phone' => null,
        'postal_code' => '67000',
    ]);
    $this->app->instance(Qualifier::class, new FakeQualifier(result: new QualificationResult(
        relevance: Relevance::Relevant,
        projectType: 'travaux',
        summary: 'Une demande.',
    )));

    (new QualifyContactRequest)->handle($request);

    expect($request->fresh()->lead_quality)->toBe(LeadQuality::Incomplete);
});

it('computes priority deterministically', function (
    Relevance $relevance,
    ?int $estimateMax,
    Deadline $deadline,
    bool $complete,
    Priority $expected,
) {
    ArtisanProfile::factory()->create();
    $request = ContactRequest::factory()->unqualified()->create([
        'deadline' => $deadline,
        'phone' => $complete ? '0102030405' : null,
        'postal_code' => $complete ? '67000' : null,
    ]);
    $this->app->instance(Qualifier::class, new FakeQualifier(result: new QualificationResult(
        relevance: $relevance,
        projectType: 'travaux',
        summary: 'Une demande.',
        estimatedAmountMin: $estimateMax,
        estimatedAmountMax: $estimateMax,
    )));

    (new QualifyContactRequest)->handle($request);

    expect($request->fresh()->priority)->toBe($expected);
})->with([
    // relevance floors to low whatever the score.
    'spam → low' => [Relevance::Spam, 5000, Deadline::Immediate, true, Priority::Low],
    'out of trade → low' => [Relevance::OutOfTrade, 5000, Deadline::Immediate, true, Priority::Low],
    // score = estimation(4/2/0) + deadline(2/1/0) + complete(1).
    'high estimate dominates (4+2+1=7)' => [Relevance::Relevant, 4000, Deadline::Immediate, true, Priority::High],
    'score five is high (4+1+0=5)' => [Relevance::Relevant, 4000, Deadline::WithinOneMonth, false, Priority::High],
    'medium estimate (2+0+1=3)' => [Relevance::Relevant, 1000, Deadline::NotUrgent, true, Priority::Medium],
    'threshold two is medium (2+0+0=2)' => [Relevance::Relevant, 1000, Deadline::NotUrgent, false, Priority::Medium],
    'low everything (0)' => [Relevance::Relevant, 300, Deadline::NotUrgent, false, Priority::Low],
    'null estimate not urgent incomplete (0)' => [Relevance::Relevant, null, Deadline::NotUrgent, false, Priority::Low],
]);

it('leaves the request unqualified when no artisan profile exists', function () {
    $request = ContactRequest::factory()->unqualified()->create();
    // Throwing qualifier proves qualify() is never called without a profile.
    $this->app->instance(Qualifier::class, FakeQualifier::throwing());

    $result = (new QualifyContactRequest)->handle($request);

    expect($result->qualified_at)->toBeNull()
        ->and($request->fresh()->relevance)->toBeNull();
});

it('leaves the request unqualified and rethrows when the qualifier fails', function () {
    ArtisanProfile::factory()->create();
    $request = ContactRequest::factory()->unqualified()->create();
    $this->app->instance(Qualifier::class, FakeQualifier::throwing());

    expect(fn () => (new QualifyContactRequest)->handle($request))->toThrow(RuntimeException::class);

    expect($request->fresh()->qualified_at)->toBeNull()
        ->and($request->fresh()->relevance)->toBeNull();
});
