<?php

declare(strict_types=1);

namespace App\Infrastructure\Job;

use App\Application\UseCase\SendWelcomeEmail\Request;
use App\Application\UseCase\SendWelcomeEmail\UseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Thin async wrapper — owns no business logic.
 *
 * Carries the minimum data needed to invoke the SendWelcomeEmail Use Case
 * from a worker. Equivalent to a Symfony Messenger MessageHandler.
 */
final class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $userUuid,
    ) {}

    public function handle(UseCase $useCase): void
    {
        $useCase->execute(new Request(userUuid: $this->userUuid));
    }
}
