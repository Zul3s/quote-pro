<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ContactRequest;
use Illuminate\Foundation\Events\Dispatchable;

final class ContactRequestSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly ContactRequest $contactRequest,
    ) {}
}
