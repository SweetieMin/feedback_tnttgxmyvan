<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Exceptions\FeedbackConflictException;
use App\Http\Requests\ReopenFeedbackRequest;
use App\Http\Requests\UpdateFeedbackStatusRequest;
use App\Models\Feedback;
use App\Services\FeedbackStatusService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FeedbackStatusController extends Controller
{
    public function __construct(private readonly FeedbackStatusService $statusService) {}

    /**
     * Chuyển trạng thái "xuôi" (reviewing→in_progress→resolved) hoặc đóng
     * sớm (new/reviewing/in_progress→closed), theo bảng chuyển trạng thái 3.1.
     */
    public function update(UpdateFeedbackStatusRequest $request, Feedback $feedback): RedirectResponse
    {
        try {
            $this->statusService->changeStatus(
                $feedback,
                $request->user(),
                FeedbackStatus::from($request->string('to_status')->toString()),
                $request->filled('reason') ? $request->string('reason')->toString() : null,
            );
        } catch (FeedbackConflictException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã cập nhật trạng thái góp ý.')]);

        return back();
    }

    /**
     * Mở lại góp ý đã giải quyết/đã đóng (resolved|closed → in_progress).
     */
    public function reopen(ReopenFeedbackRequest $request, Feedback $feedback): RedirectResponse
    {
        try {
            $this->statusService->reopen($feedback, $request->user(), $request->string('reason')->toString());
        } catch (FeedbackConflictException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã mở lại góp ý.')]);

        return back();
    }
}
