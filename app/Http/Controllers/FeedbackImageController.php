<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackImageController extends Controller
{
    /**
     * Nhận ảnh chèn trong trình soạn góp ý, trả về đường dẫn để Tiptap gắn vào
     * node `image`. Đường dẫn này là dạng duy nhất ValidTiptapDocument chấp
     * nhận cho `image.src`.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ], [
            'image.required' => 'Vui lòng chọn ảnh.',
            'image.mimes' => 'Chỉ chấp nhận ảnh JPG, PNG, GIF hoặc WebP.',
            'image.max' => 'Ảnh tối đa 5 MB.',
        ]);

        $path = $request->file('image')?->store('feedback-images', 'public');

        abort_if(! is_string($path), 500, 'Không lưu được ảnh.');

        return response()->json(['url' => '/storage/'.$path], 201);
    }
}
