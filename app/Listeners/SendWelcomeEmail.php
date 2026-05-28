<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreated;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;

final class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        // WelcomeEmail is ShouldQueue, so send() pushes it onto the queue.
        Mail::to($event->user->email)->send(new WelcomeEmail($event->user));
    }
}
