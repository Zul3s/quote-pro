<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Validator;

use App\Domain\Exception\ValidationsException;
use App\Domain\Model\ValidationReason;
use App\Domain\Service\RequestValidatorInterface;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class SpatieRequestValidator implements RequestValidatorInterface
{
    public function validate(object $request): void
    {
        if (! $request instanceof Data) {
            throw new InvalidArgumentException(sprintf(
                'SpatieRequestValidator expects a %s instance, %s given.',
                Data::class,
                $request::class,
            ));
        }

        try {
            $request::validate($request->toArray());
        } catch (ValidationException $e) {
            $reasons = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $reasons[] = new ValidationReason($message, $field);
                }
            }

            throw new ValidationsException($reasons);
        }
    }
}
