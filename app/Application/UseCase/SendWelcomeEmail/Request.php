<?php

declare(strict_types=1);

namespace App\Application\UseCase\SendWelcomeEmail;

use App\Application\UseCase\AbstractRequest;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;

final class Request extends AbstractRequest
{
    public function __construct(
        #[Required, Uuid]
        public string $userUuid,
    ) {}
}
