<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreFeedbackRequest;
use App\Http\Requests\UpdateFeedbackRequest;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\FeedbackMessage;
use App\Models\User;
use App\Notifications\FeedbackPushNotification;
use App\Services\FeedbackNotificationRecipients;
use App\Services\FeedbackStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    /**
     * Danh sách góp ý của chính người gọi, lọc theo trạng thái/danh mục.
     */
    public function index(Request $request): Response
    {
        $feedbacks = $request->user()
            ->feedbacksSubmitted()
            ->select(['id', 'title', 'excerpt', 'status', 'category_id', 'assigned_to', 'created_at'])
            ->with(['category:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->latest()
            ->get();

        return Inertia::render('feedback/index', [
            'feedbacks' => $feedbacks,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'category_id']),
        ]);
    }

    /**
     * Form gửi góp ý mới.
     */
    public function create(): Response
    {
        return Inertia::render('feedback/create', [
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Tạo góp ý mới, luôn ở trạng thái "new" và ghi lịch sử xử lý đầu tiên.
     */
    public function store(StoreFeedbackRequest $request, FeedbackNotificationRecipients $recipients): RedirectResponse
    {
        /** @var array<string, mixed> $content */
        $content = $request->input('content');

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'category_id' => $request->integer('category_id'),
            'title' => $request->string('title')->toString(),
            'content' => $content,
            'excerpt' => Feedback::excerptFromContent($content),
            'status' => FeedbackStatus::New,
        ]);

        FeedbackActivity::create([
            'feedback_id' => $feedback->id,
            'actor_id' => $request->user()->id,
            'type' => ActivityType::StatusChange,
            'from_value' => null,
            'to_value' => FeedbackStatus::New->value,
        ]);

        Notification::send(
            $recipients->for($feedback, $request->user(), includeOwner: false),
            new FeedbackPushNotification($feedback, 'created'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã gửi góp ý.')]);

        return to_route('feedback.show', $feedback);
    }

    /**
     * Chi tiết góp ý — chặn truy cập trực tiếp nếu không có quyền xem (4.3).
     */
    public function show(Request $request, Feedback $feedback, FeedbackStatusService $statusService): Response
    {
        Gate::authorize('view', $feedback);

        $feedback->load([
            'category:id,name',
            'sender:id,name,avatar_path',
            'assignee:id,name,avatar_path',
            'activities' => fn ($query) => $query->oldest()->with('actor:id,name,avatar_path'),
            'messages' => fn ($query) => $query->visibleTo($request->user())->oldest()->with('author:id,name,avatar_path'),
        ]);

        $user = $request->user();
        $canUpdate = $user->can('update', $feedback);
        $canChangeStatus = $user->can('changeStatus', $feedback);
        $canReassign = $user->can('reassign', $feedback);

        return Inertia::render('feedback/show', [
            'feedback' => $feedback,
            'can' => [
                'update' => $canUpdate,
                'reopen' => $user->can('reopen', $feedback),
                'selfAssign' => $user->can('selfAssign', $feedback),
                'changeStatus' => $canChangeStatus,
                'reassign' => $canReassign,
                'addInternalNote' => $user->can('viewInternal', FeedbackMessage::class),
            ],
            'transitions' => $canChangeStatus
                ? array_map(fn (FeedbackStatus $status) => $status->value, $statusService->allowedTransitions($feedback))
                : [],
            'categories' => $canUpdate
                ? Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'handlers' => $canReassign
                ? User::query()->where('role', UserRole::Handler->value)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /**
     * Sửa nội dung gốc — Policy đã chặn trừ khi còn trạng thái "new" và là chủ sở hữu (A10).
     */
    public function update(UpdateFeedbackRequest $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('content', $data)) {
            $data['excerpt'] = Feedback::excerptFromContent($data['content']);
        }

        $feedback->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã cập nhật góp ý.')]);

        return to_route('feedback.show', $feedback);
    }
}
