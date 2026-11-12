<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        if ($user->must_change_password && Hash::check($input['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => 'Mật khẩu mới phải khác mật khẩu tạm.']);
        }

        $user->forceFill([
            'password' => $input['password'],
            'must_change_password' => false,
        ])->save();
    }
}
