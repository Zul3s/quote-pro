<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\CreateUserData;
use App\Events\UserCreated;
use App\Models\User;
use App\Rules\EmailIsUnique;
use Illuminate\Support\Facades\Validator;

final readonly class CreateUser
{
    public function handle(CreateUserData $data): User
    {
        // Business rule (the Action is the authority, regardless of caller).
        Validator::make(
            ['email' => $data->email],
            ['email' => [new EmailIsUnique]],
        )->validate();

        $user = User::create([
            'email' => $data->email,
            'first_name' => $data->firstName,
            'last_name' => $data->lastName,
        ]);

        UserCreated::dispatch($user);

        return $user;
    }
}
