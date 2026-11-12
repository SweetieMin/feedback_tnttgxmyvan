<?php

namespace App\Http\Requests\Admin;

use App\Services\AdminImage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAvatarRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['avatar' => ['required', ...AdminImage::RULES]];
    }
}
