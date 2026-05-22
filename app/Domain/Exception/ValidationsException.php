<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\Model\ValidationReason;
use RuntimeException;

final class ValidationsException extends RuntimeException
{
    /** @var list<ValidationReason> */
    public readonly array $reasons;

    /**
     * @param  list<ValidationReason>  $reasons
     */
    public function __construct(array $reasons)
    {
        $this->reasons = $reasons;
        parent::__construct(implode(', ', array_map(
            static fn (ValidationReason $r) => $r->messageKey,
            $reasons,
        )));
    }
}
