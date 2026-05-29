<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Qualifier;
use App\Data\SubmitContactRequestData;
use App\Events\ContactRequestSubmitted;
use App\Models\ArtisanProfile;
use App\Models\ContactRequest;
use App\Rules\DescriptionIsSufficient;
use Illuminate\Support\Facades\Validator;

final readonly class SubmitContactRequest
{
    public function handle(SubmitContactRequestData $data): ContactRequest
    {
        // Soft sufficiency gate: skipped when the prospect acknowledged they
        // cannot provide the missing details. The rule is fail-open, so an LLM
        // outage never blocks a lead (see DescriptionIsSufficient).
        if (! $data->acknowledgeMissingInfo) {
            Validator::make(
                ['description' => $data->description],
                ['description' => [new DescriptionIsSufficient(app(Qualifier::class), ArtisanProfile::first())]],
            )->validate();
        }

        $contactRequest = ContactRequest::create([
            'name' => $data->name,
            'email' => $data->email,
            'request_type' => $data->requestType,
            'deadline' => $data->deadline,
            'description' => $data->description,
            'phone' => $data->phone,
            'postal_code' => $data->postalCode,
            'missing_info_acknowledged' => $data->acknowledgeMissingInfo,
        ]);

        ContactRequestSubmitted::dispatch($contactRequest);

        return $contactRequest;
    }
}
