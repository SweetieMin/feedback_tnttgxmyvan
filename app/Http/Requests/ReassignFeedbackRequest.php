<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reassign', $this->route('feedback'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Handler->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.required' => 'Vui lòng chọn người xử lý.',
            'assigned_to.integer' => 'Người xử lý không hợp lệ.',
            'assigned_to.exists' => 'Người được chọn không phải là người xử lý.',
        ];
    }
}
