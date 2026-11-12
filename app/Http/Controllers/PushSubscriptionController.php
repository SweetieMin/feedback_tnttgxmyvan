<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * Đăng ký (hoặc cập nhật) một subscription web push cho thiết bị hiện tại.
     *
     * Gọi bằng `fetch` từ resources/js/lib/push-subscription.ts (không qua
     * Inertia) nên trả 204, giống ngoại lệ unreadCount() ở NotificationController.
     */
    public function store(StorePushSubscriptionRequest $request): Response
    {
        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $request->string('endpoint')->toString()],
            [
                'public_key' => $request->input('keys.p256dh'),
                'auth_token' => $request->input('keys.auth'),
                'content_encoding' => $request->string('contentEncoding', 'aes128gcm')->toString(),
            ],
        );

        return response()->noContent();
    }

    /**
     * Huỷ đăng ký — chỉ xoá subscription của chính người gọi (quan hệ đã tự scope theo user_id).
     */
    public function destroy(Request $request): Response
    {
        $request->user()->pushSubscriptions()
            ->where('endpoint', $request->string('endpoint')->toString())
            ->delete();

        return response()->noContent();
    }
}
