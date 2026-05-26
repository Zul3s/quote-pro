<?php

declare(strict_types=1);

namespace App\Application\UseCase\SubmitContactRequest;

use App\Application\UseCase\AbstractRequest;
use App\Domain\Model\Deadline;
use App\Domain\Model\RequestType;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

final class Request extends AbstractRequest
{
    public function __construct(
        #[Required, StringType, Max(150)]
        public string $name,

        #[Required, Email, Max(180)]
        public string $email,

        #[Required, Enum(RequestType::class)]
        public RequestType $requestType,

        #[Required, Enum(Deadline::class)]
        public Deadline $deadline,

        #[Required, StringType, Max(5000)]
        public string $description,

        #[Nullable, StringType, Max(40)]
        public ?string $phone = null,

        #[Nullable, StringType, Max(20)]
        public ?string $postalCode = null,
    ) {}
}
