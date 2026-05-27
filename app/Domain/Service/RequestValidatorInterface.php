<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Exception\ValidationsException;

interface RequestValidatorInterface
{
    /**
     * @throws ValidationsException when the request fails its declarative rules
     */
    public function validate(object $request): void;
}
