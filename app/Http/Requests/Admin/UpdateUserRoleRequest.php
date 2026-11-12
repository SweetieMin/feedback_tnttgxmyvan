<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->user()->can('updateRole', $target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', new Enum(UserRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Vui lòng chọn vai trò.',
            'role.enum' => 'Vai trò không hợp lệ.',
        ];
    }

    /**
     * Admin tự hạ vai trò của mình có thể làm hệ thống mất quản trị viên
     * cuối cùng — chặn ở server, không chỉ khoá ô chọn trên giao diện.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $target */
                $target = $this->route('user');

                if ($target->is($this->user())) {
                    $validator->errors()->add('role', 'Bạn không thể tự đổi vai trò của chính mình.');
                }
            },
        ];
    }

    public function role(): UserRole
    {
        return UserRole::from($this->string('role')->toString());
    }
}
