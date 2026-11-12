<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends StoreUserRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $rules = parent::rules();
        unset($rules['password']);
        $rules['email'] = ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($target)];
        $rules['role'][0] = 'sometimes';

        if ($target->is($this->user())) {
            $rules['role'][] = Rule::in([$target->role->value]);
        }

        return $rules;
    }
}
