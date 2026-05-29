<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\QualifyContactRequest;
use App\Events\ContactRequestSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Runs the qualification asynchronously when a contact request is submitted.
 * Queued so it never blocks the submission; if the Action throws (LLM down) the
 * job is retried by the queue and the row stays unqualified meanwhile.
 */
final class QualifySubmittedContactRequest implements ShouldQueue
{
    public function handle(ContactRequestSubmitted $event): void
    {
        app(QualifyContactRequest::class)->handle($event->contactRequest);
    }
}
