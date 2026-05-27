<?php

declare(strict_types=1);

use App\Events\UserCreated;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| SendWelcomeEmail Listener (on UserCreated)
|--------------------------------------------------------------------------
*/

it('queues a welcome email when a user is created', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'welcome@example.com',
        'first_name' => 'Welcome',
        'last_name' => 'User',
    ]);

    UserCreated::dispatch($user);

    Mail::assertQueued(
        WelcomeEmail::class,
        fn (WelcomeEmail $mail) => $mail->hasTo('welcome@example.com') && $mail->user->is($user),
    );
});
