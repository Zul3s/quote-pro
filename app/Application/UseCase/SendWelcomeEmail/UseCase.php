<?php

declare(strict_types=1);

namespace App\Application\UseCase\SendWelcomeEmail;

use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\MailerInterface;
use Ramsey\Uuid\Uuid;

final readonly class UseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private MailerInterface $mailer,
    ) {}

    public function execute(Request $request): void
    {
        $user = $this->userRepository->findByUuid(Uuid::fromString($request->userUuid));

        if ($user === null) {
            throw EntityNotFoundException::forClass(
                'User',
                $request->userUuid,
            );
        }

        $this->mailer->send(
            toEmail: $user->getEmail(),
            toName: trim(($user->getFirstName() ?? '').' '.($user->getLastName() ?? '')) ?: null,
            subject: 'Bienvenue sur Quote Plus !',
            view: 'emails.welcome',
            context: [
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
            ],
        );
    }
}
