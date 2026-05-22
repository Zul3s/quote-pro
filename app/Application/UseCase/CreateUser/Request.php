<?php

declare(strict_types=1);

namespace App\Application\UseCase\CreateUser;

use App\Application\UseCase\AbstractRequest;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;

final class Request extends AbstractRequest
{
    public function __construct(
        #[Required, Email, Max(180)]
        public string $email,

        #[Nullable, Max(100)]
        public ?string $firstName = null,

        #[Nullable, Max(100)]
        public ?string $lastName = null,
    ) {}
}
