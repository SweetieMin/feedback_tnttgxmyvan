<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use App\Services\AdminImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['nullable', ...AdminImage::RULES],
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên danh mục.',
            'name.max' => 'Tên danh mục tối đa 100 ký tự.',
            'name.unique' => 'Đã có danh mục mang tên này.',
        ];
    }
}
