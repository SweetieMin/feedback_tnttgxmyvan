<?php

namespace App\Http\Requests;

use App\Models\Feedback;
use App\Rules\ValidTiptapDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Feedback::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'array', new ValidTiptapDocument('feedback')],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'title.max' => 'Tiêu đề tối đa 255 ký tự.',
            'content.required' => 'Vui lòng nhập nội dung góp ý.',
            'content.array' => 'Nội dung góp ý không hợp lệ.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.integer' => 'Danh mục không hợp lệ.',
            'category_id.exists' => 'Danh mục không tồn tại hoặc đã ngừng sử dụng.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['content' => 'góp ý'];
    }
}
