<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    /**
     * Hàng đợi chung cho handler/admin — dùng chung cho cả /queue và
     * /admin/feedback, `scope` chỉ để frontend quyết định hiển thị thêm
     * điều khiển "đổi người phụ trách" (chỉ admin mới được phép thực hiện,
     * đã chặn ở FeedbackPolicy::reassign).
     */
    public function index(Request $request): Response
    {
        $assignee = $request->string('assignee')->toString();

        $feedbacks = Feedback::query()
            ->select(['id', 'user_id', 'title', 'excerpt', 'status', 'category_id', 'assigned_to', 'created_at'])
            ->with(['category:id,name', 'sender:id,name', 'assignee:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($assignee === 'unassigned', fn ($query) => $query->whereNull('assigned_to'))
            ->when($assignee === 'me', fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->when(ctype_digit($assignee), fn ($query) => $query->where('assigned_to', (int) $assignee))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->latest()
            ->get();

        return Inertia::render('feedback/queue', [
            'feedbacks' => $feedbacks,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'handlers' => User::query()->where('role', UserRole::Handler->value)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['status', 'assignee', 'category_id']),
            'scope' => $request->user()->role->value,
        ]);
    }
}
