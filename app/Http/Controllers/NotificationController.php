<?php

namespace App\Http\Controllers;

use App\Models\FeedbackNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Danh sách thông báo của người dùng hiện tại, mới nhất trước (mục 1.3).
     */
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->feedbackNotifications()
            ->with('feedback:id,title')
            ->latest()
            ->get();

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * JSON nhỏ cho badge chuông, được gọi định kỳ bằng `fetch` — ngoại lệ
     * duy nhất không trả Inertia response (mục 1.3).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()
            ->feedbackNotifications()
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Đánh dấu đã đọc — chỉ chủ sở hữu thông báo được gọi (mục 1.3).
     */
    public function markRead(Request $request, FeedbackNotification $notification): RedirectResponse
    {
        Gate::authorize('markRead', $notification);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return back();
    }
}
