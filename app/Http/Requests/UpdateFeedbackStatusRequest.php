<?php

namespace App\Http\Requests;

use App\Enums\FeedbackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeedbackStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('feedback'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_status' => [
                'required',
                Rule::in([
                    FeedbackStatus::InProgress->value,
                    FeedbackStatus::Resolved->value,
                    FeedbackStatus::Closed->value,
                ]),
            ],
            'reason' => ['required_if:to_status,'.FeedbackStatus::Closed->value, 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_status.required' => 'Vui lòng chọn trạng thái mới.',
            'to_status.in' => 'Trạng thái mới không hợp lệ.',
            'reason.required_if' => 'Vui lòng nhập lý do đóng góp ý.',
            'reason.max' => 'Lý do tối đa 255 ký tự.',
        ];
    }
}
