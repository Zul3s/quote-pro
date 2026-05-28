<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\SubmitContactRequestData;
use App\Events\ContactRequestSubmitted;
use App\Models\ContactRequest;

final readonly class SubmitContactRequest
{
    public function handle(SubmitContactRequestData $data): ContactRequest
    {
        $contactRequest = ContactRequest::create([
            'name' => $data->name,
            'email' => $data->email,
            'request_type' => $data->requestType,
            'deadline' => $data->deadline,
            'description' => $data->description,
            'phone' => $data->phone,
            'postal_code' => $data->postalCode,
        ]);

        ContactRequestSubmitted::dispatch($contactRequest);

        return $contactRequest;
    }
}
