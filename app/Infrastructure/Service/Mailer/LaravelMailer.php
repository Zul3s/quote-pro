<?php

declare(strict_types=1);

namespace App\Infrastructure\Service\Mailer;

use App\Domain\Service\MailerInterface;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Mail\Message;

final readonly class LaravelMailer implements MailerInterface
{
    public function __construct(
        private MailFactory $mailer,
    ) {}

    public function send(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $view,
        array $context = [],
    ): void {
        $this->mailer->mailer()->send(
            $view,
            $context,
            function (Message $message) use ($toEmail, $toName, $subject): void {
                $message->to($toEmail, $toName)->subject($subject);
            },
        );
    }
}
