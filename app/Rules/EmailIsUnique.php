<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Business rule (successor of the CanCreateUser Specification): the email must
 * not already belong to an active (non soft-deleted) user. Reusable across
 * Actions and composable into any validator.
 */
final class EmailIsUnique implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (User::whereEmail($value)->exists()) {
            $fail('validation.email.already_used')->translate();
        }
    }
}
