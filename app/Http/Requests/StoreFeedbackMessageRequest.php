<?php

namespace App\Http\Requests;

use App\Enums\MessageVisibility;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Rules\ValidTiptapDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Feedback $feedback */
        $feedback = $this->route('feedback');

        return $this->user()->can('create', [FeedbackMessage::class, $feedback, $this->visibility()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'array', new ValidTiptapDocument('message')],
            'visibility' => ['sometimes', Rule::in([MessageVisibility::Public->value, MessageVisibility::Internal->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Vui lòng nhập nội dung trao đổi.',
            'body.array' => 'Nội dung trao đổi không hợp lệ.',
            'visibility.in' => 'Chế độ hiển thị không hợp lệ.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'trao đổi'];
    }

    public function visibility(): MessageVisibility
    {
        return MessageVisibility::tryFrom((string) $this->input('visibility', MessageVisibility::Public->value))
            ?? MessageVisibility::Public;
    }
}
