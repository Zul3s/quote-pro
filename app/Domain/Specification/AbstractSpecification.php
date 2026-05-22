<?php

declare(strict_types=1);

namespace App\Domain\Specification;

use App\Domain\Exception\ValidationsException;
use App\Domain\Model\ValidationReason;

abstract class AbstractSpecification
{
    /**
     * @param  list<ValidationReason>  $reasons
     */
    protected function toReturn(array $reasons, bool $exceptionMode): bool
    {
        if ($exceptionMode === true && count($reasons) > 0) {
            throw new ValidationsException($reasons);
        }

        return count($reasons) === 0;
    }
}
