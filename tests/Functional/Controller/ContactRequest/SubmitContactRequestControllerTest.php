<?php

declare(strict_types=1);

use App\Contracts\Qualifier;
use App\Events\ContactRequestSubmitted;
use App\Models\ArtisanProfile;
use Illuminate\Support\Facades\Event;
use Tests\Fakes\FakeQualifier;

/*
|--------------------------------------------------------------------------
| SubmitContactRequestController — HTTP layer
|--------------------------------------------------------------------------
| Transport concerns only: status, redirect target, session flash/errors,
| validation → HTTP status, JSON body shape. Persistence, the soft gate and
| its fail-open behaviour are covered in
| tests/Feature/Action/SubmitContactRequestTest.php.
*/

function submitPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Marie Dupont',
        'email' => 'marie.dupont@example.com',
        'requestType' => 'quote',
        'deadline' => 'immediate',
        'description' => 'Repeindre 40 m² de murs et 18 m² de plafond dans le séjour.',
    ], $overrides);
}

it('redirects to the thank-you page with a flash success on a sufficient submission', function () {
    Event::fake([ContactRequestSubmitted::class]);
    ArtisanProfile::factory()->create();
    $this->app->instance(Qualifier::class, FakeQualifier::sufficient());

    $this->from('/')
        ->post('/contact-requests', submitPayload())
        ->assertRedirect('/thank-you')
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();
});

it('returns a 201 JSON payload on a sufficient API submission', function () {
    Event::fake([ContactRequestSubmitted::class]);
    ArtisanProfile::factory()->create();
    $this->app->instance(Qualifier::class, FakeQualifier::sufficient());

    $this->postJson('/contact-requests', submitPayload())
        ->assertStatus(201)
        ->assertJsonStructure(['uuid', 'name', 'email', 'request_type', 'deadline'])
        ->assertJsonFragment(['email' => 'marie.dupont@example.com']);
});

it('redirects back with the missing-info message when the description is insufficient', function () {
    Event::fake([ContactRequestSubmitted::class]);
    ArtisanProfile::factory()->create();
    $this->app->instance(
        Qualifier::class,
        FakeQualifier::insufficient('Précisez la surface en m² et la nature des travaux.'),
    );

    $this->from('/')
        ->post('/contact-requests', submitPayload(['description' => 'Des travaux chez moi.']))
        ->assertRedirect('/')
        ->assertSessionHasErrors([
            'description' => 'Précisez la surface en m² et la nature des travaux.',
        ]);
});

it('passes the submission through when missing info is acknowledged, skipping the gate', function () {
    Event::fake([ContactRequestSubmitted::class]);
    // A throwing qualifier proves the gate is never invoked at the HTTP layer.
    $this->app->instance(Qualifier::class, FakeQualifier::throwing());

    $this->from('/')
        ->post('/contact-requests', submitPayload([
            'description' => 'Des travaux chez moi.',
            'acknowledgeMissingInfo' => true,
        ]))
        ->assertRedirect('/thank-you')
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();
});

it('returns 422 with field errors when a required field is missing (JSON)', function () {
    $this->postJson('/contact-requests', submitPayload(['email' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
