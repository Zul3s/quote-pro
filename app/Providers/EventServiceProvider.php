<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ContactRequestSubmitted;
use App\Listeners\QualifySubmittedContactRequest;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Explicit Domain Event → Listener wiring (no auto-discovery, by preference).
 */
final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ContactRequestSubmitted::class => [
            QualifySubmittedContactRequest::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
