<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueController extends Controller
{
    /**
     * Ba tab của hàng đợi và các trạng thái thuộc về từng tab.
     *
     * @var array<string, list<FeedbackStatus>>
     */
    private const TABS = [
        'open' => [FeedbackStatus::New, FeedbackStatus::Reviewing, FeedbackStatus::InProgress],
        'resolved' => [FeedbackStatus::Resolved],
        'closed' => [FeedbackStatus::Closed],
    ];

    /**
     * Hàng đợi chung cho handler/admin — dùng chung cho cả /queue và
     * /admin/feedback, `scope` chỉ để frontend quyết định hiển thị thêm
     * điều khiển "đổi người phụ trách" (chỉ admin mới được phép thực hiện,
     * đã chặn ở FeedbackPolicy::reassign).
     */
    public function index(Request $request): Response
    {
        $status = FeedbackStatus::tryFrom($request->string('status')->toString());
        $tab = $this->resolveTab($request->string('tab')->toString(), $status);

        // `status` chỉ có nghĩa khi nằm trong tab đang xem; nếu lệch tab
        // (vd. ?tab=closed&status=new) thì bỏ qua thay vì trả danh sách rỗng.
        if ($status !== null && ! in_array($status, self::TABS[$tab], true)) {
            $status = null;
        }

        $feedbacks = $this->filteredQuery($request)
            ->select(['id', 'user_id', 'title', 'excerpt', 'status', 'category_id', 'assigned_to', 'created_at'])
            ->with(['category:id,name', 'sender:id,name', 'assignee:id,name'])
            ->whereIn('status', self::TABS[$tab])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->get();

        return Inertia::render('feedback/queue', [
            'feedbacks' => $feedbacks,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'handlers' => User::query()->where('role', UserRole::Handler->value)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                ...$request->only(['assignee', 'category_id']),
                ...($status !== null ? ['status' => $status->value] : []),
            ],
            'tab' => $tab,
            'tabCounts' => $this->tabCounts($request),
            'scope' => $request->user()->role->value,
        ]);
    }

    /**
     * Tab tường minh được ưu tiên; nếu không có thì suy ra từ `status` để các
     * liên kết cũ dạng /queue?status=resolved (dashboard) vẫn mở đúng tab.
     */
    private function resolveTab(string $requested, ?FeedbackStatus $status): string
    {
        if (array_key_exists($requested, self::TABS)) {
            return $requested;
        }

        foreach (self::TABS as $tab => $statuses) {
            if ($status !== null && in_array($status, $statuses, true)) {
                return $tab;
            }
        }

        return 'open';
    }

    /**
     * Bộ lọc người phụ trách + danh mục — áp dụng như nhau cho danh sách và
     * cho số đếm trên từng tab.
     *
     * @return Builder<Feedback>
     */
    private function filteredQuery(Request $request): Builder
    {
        $assignee = $request->string('assignee')->toString();

        return Feedback::query()
            ->when($assignee === 'unassigned', fn (Builder $query) => $query->whereNull('assigned_to'))
            ->when($assignee === 'me', fn (Builder $query) => $query->where('assigned_to', $request->user()->id))
            ->when(ctype_digit($assignee), fn (Builder $query) => $query->where('assigned_to', (int) $assignee))
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('category_id', $request->integer('category_id')));
    }

    /**
     * @return array<string, int>
     */
    private function tabCounts(Request $request): array
    {
        $byStatus = $this->filteredQuery($request)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_map(
            fn (array $statuses): int => array_sum(array_map(
                fn (FeedbackStatus $status): int => (int) ($byStatus[$status->value] ?? 0),
                $statuses,
            )),
            self::TABS,
        );
    }
}
