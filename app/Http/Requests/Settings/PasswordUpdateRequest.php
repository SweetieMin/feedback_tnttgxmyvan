<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $passwordRules = $this->passwordRules();

        if ($this->routeIs('password.first.update')) {
            $passwordRules[] = 'different:current_password';
        }

        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $passwordRules,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['password.different' => 'Mật khẩu mới phải khác mật khẩu tạm.'];
    }
}
