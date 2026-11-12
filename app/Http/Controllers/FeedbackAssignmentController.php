<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\FeedbackConflictException;
use App\Http\Requests\ReassignFeedbackRequest;
use App\Models\Feedback;
use App\Models\User;
use App\Services\FeedbackStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedbackAssignmentController extends Controller
{
    public function __construct(private readonly FeedbackStatusService $statusService) {}

    /**
     * Handler tự nhận một góp ý chưa có người phụ trách. Chỉ chặn theo vai
     * trò ở đây: góp ý vừa bị người khác nhận (trang đã cũ) là xung đột
     * nghiệp vụ — service báo conflict để hiện thông báo, không phải 403 (6.2).
     */
    public function selfAssign(Request $request, Feedback $feedback): RedirectResponse
    {
        abort_unless($request->user()->role === UserRole::Handler, 403);

        try {
            $this->statusService->selfAssign($feedback, $request->user());
        } catch (FeedbackConflictException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bạn đã nhận xử lý góp ý này.')]);

        return back();
    }

    /**
     * Quản trị viên đổi người phụ trách sang handler khác.
     */
    public function reassign(ReassignFeedbackRequest $request, Feedback $feedback): RedirectResponse
    {
        $newHandler = User::findOrFail($request->integer('assigned_to'));

        try {
            $this->statusService->reassign($feedback, $request->user(), $newHandler);
        } catch (FeedbackConflictException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã đổi người phụ trách.')]);

        return back();
    }
}
