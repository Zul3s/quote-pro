<?php

declare(strict_types=1);

use App\Models\ArtisanProfile;
use App\Services\OllamaQualifier;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| OllamaQualifier — submission gate (assess)
|--------------------------------------------------------------------------
|
| Pure unit coverage: the outbound Ollama HTTP call is faked, the profile is
| an unsaved Active Record (no DB). These lock the regression fix:
|   - the artisan rate card (services) is NOT injected into the assess prompt
|     (it used to leak invented needs, e.g. "fuite", into the verdict);
|   - the call is deterministic (options.temperature = 0).
|
*/

function fakeOllama(array $verdict): void
{
    Http::fake([
        '*' => Http::response(['response' => json_encode($verdict)]),
    ]);
}

it('keeps the rate card out of the assess prompt and decodes deterministically', function () {
    fakeOllama(['sufficient' => false, 'message' => 'précisez une surface en m²']);

    $profile = new ArtisanProfile([
        'professions' => ['plomberie', 'chauffage'],
        'services' => 'SENTINEL_BAREME réparation de fuite ~ 80 € / h',
    ]);

    $result = (new OllamaQualifier)->assess('Des travaux chez moi sur 100 m².', $profile);

    expect($result->sufficient)->toBeFalse()
        ->and($result->message)->toBe('précisez une surface en m²');

    Http::assertSent(function ($request) {
        $prompt = data_get($request, 'prompt');

        return $request->url() === 'http://localhost:11434/api/generate'
            && data_get($request, 'options.temperature') === 0
            && data_get($request, 'format') === 'json'
            && ! str_contains($prompt, 'SENTINEL_BAREME')
            && str_contains($prompt, 'Des travaux chez moi sur 100 m².')
            && str_contains($prompt, 'plomberie, chauffage');
    });
});

it('maps a sufficient verdict to a null message', function () {
    fakeOllama(['sufficient' => true, 'message' => '']);

    $result = (new OllamaQualifier)->assess('Pose de 3 radiateurs.', new ArtisanProfile([
        'professions' => ['chauffage'],
        'services' => 'Pose de radiateur ~ 200 €',
    ]));

    expect($result->sufficient)->toBeTrue()
        ->and($result->message)->toBeNull();
});
