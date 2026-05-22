<?php

declare(strict_types=1);

namespace App\Domain\Service;

interface MailerInterface
{
    /**
     * @param  array<string, mixed>  $context  Variables passed to the template/view.
     */
    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $view,
        array $context = [],
    ): void;
}
