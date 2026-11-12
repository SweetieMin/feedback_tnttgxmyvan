<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReopenFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reopen', $this->route('feedback'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do mở lại.',
            'reason.max' => 'Lý do tối đa 255 ký tự.',
        ];
    }
}
