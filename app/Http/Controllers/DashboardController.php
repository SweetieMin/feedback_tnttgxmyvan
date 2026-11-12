<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Khoảng thời gian cho các chỉ số của admin, luôn tính theo tuần ISO trọn
     * vẹn để trục "Xu hướng theo tuần" liên tục — mặc định 8 tuần (mục 5.3).
     *
     * @var list<int>
     */
    private const array WEEK_OPTIONS = [4, 8, 12, 26];

    private const int DEFAULT_WEEKS = 8;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $props = match ($user->role) {
            UserRole::Employee => $this->forEmployee($user),
            UserRole::Handler => $this->forHandler(),
            UserRole::Admin => $this->forAdmin($this->selectedWeeks($request)),
        };

        return Inertia::render('dashboard', $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function forEmployee(User $user): array
    {
        return [
            'scope' => 'employee',
            'statusCounts' => $this->statusCounts(Feedback::where('user_id', $user->id)),
            'recentFeedbacks' => Feedback::where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'status', 'created_at']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forHandler(): array
    {
        return [
            'scope' => 'handler',
            'newCount' => Feedback::where('status', FeedbackStatus::New->value)->count(),
            'unassignedCount' => $this->openFeedbackQuery()->whereNull('assigned_to')->count(),
            'longestOpen' => $this->openFeedbackQuery()
                ->oldest()
                ->limit(5)
                ->get(['id', 'title', 'created_at'])
                ->map(fn (Feedback $feedback) => [
                    'id' => $feedback->id,
                    'title' => $feedback->title,
                    'daysOpen' => (int) $feedback->created_at?->diffInDays(now()),
                ])
                ->all(),
            'statusCounts' => $this->statusCounts(Feedback::query()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forAdmin(int $weeks): array
    {
        $rangeStart = $this->rangeStart($weeks);

        return [
            'scope' => 'admin',
            'weeks' => $weeks,
            'weekOptions' => self::WEEK_OPTIONS,
            'statusCounts' => $this->statusCounts(Feedback::query()),
            'workloadByAssignee' => $this->workloadByAssignee(),
            'weeklyTrend' => $this->weeklyTrend($rangeStart, $weeks),
            'avgFirstResponseSeconds' => $this->avgFirstResponseSeconds($rangeStart),
            'avgResolutionSeconds' => $this->avgResolutionSeconds($rangeStart),
        ];
    }

    /**
     * @return Builder<Feedback>
     */
    private function openFeedbackQuery(): Builder
    {
        return Feedback::whereNotIn('status', [FeedbackStatus::Resolved->value, FeedbackStatus::Closed->value]);
    }

    /**
     * Luôn đủ mọi trạng thái — 0 là giá trị hợp lệ, khác "chưa có dữ liệu" (mục 5.1).
     *
     * @param  Builder<Feedback>  $query
     * @return array<string, int>
     */
    private function statusCounts(Builder $query): array
    {
        $counts = $query
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(FeedbackStatus::cases())
            ->mapWithKeys(fn (FeedbackStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * Mọi handler đều có dòng, kể cả không có dữ liệu (mục 5.3).
     *
     * @return array<int, array{id: int, name: string, inProgress: int, resolved30d: int}>
     */
    private function workloadByAssignee(): array
    {
        $inProgress = Feedback::whereIn('status', [FeedbackStatus::Reviewing->value, FeedbackStatus::InProgress->value])
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $resolved30d = $this->resolvedActivitiesQuery()
            ->where('feedback_activities.created_at', '>=', now()->subDays(30))
            ->whereNotNull('feedbacks.assigned_to')
            ->selectRaw('feedbacks.assigned_to as assigned_to, COUNT(*) as aggregate')
            ->groupBy('feedbacks.assigned_to')
            ->pluck('aggregate', 'assigned_to');

        return User::where('role', UserRole::Handler->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $handler) => [
                'id' => $handler->id,
                'name' => $handler->name,
                'inProgress' => (int) ($inProgress[$handler->id] ?? 0),
                'resolved30d' => (int) ($resolved30d[$handler->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Tuần không có dữ liệu vẫn trả 0 để trục thời gian liên tục (mục 5.3).
     *
     * @return array<int, array{week: string, weekStart: string, received: int, resolved: int}>
     */
    private function weeklyTrend(CarbonImmutable $rangeStart, int $weeks): array
    {
        $received = Feedback::where('created_at', '>=', $rangeStart)
            ->selectRaw('YEARWEEK(created_at, 3) as yw, COUNT(*) as aggregate')
            ->groupBy('yw')
            ->pluck('aggregate', 'yw');

        $resolved = $this->resolvedActivitiesQuery()
            ->where('feedback_activities.created_at', '>=', $rangeStart)
            ->selectRaw('YEARWEEK(feedback_activities.created_at, 3) as yw, COUNT(*) as aggregate')
            ->groupBy('yw')
            ->pluck('aggregate', 'yw');

        return collect(range(0, $weeks - 1))
            ->map(function (int $offset) use ($rangeStart, $received, $resolved) {
                $weekStart = $rangeStart->addWeeks($offset);
                $yw = (int) $weekStart->format('oW');

                return [
                    'week' => $weekStart->format('o').'-W'.$weekStart->format('W'),
                    'weekStart' => $weekStart->toDateString(),
                    'received' => (int) ($received[$yw] ?? 0),
                    'resolved' => (int) ($resolved[$yw] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * `null` khi không có dòng nào có `first_response_at` — frontend hiện
     * "Chưa có dữ liệu" thay vì 0/NaN (mục 5.3).
     */
    private function avgFirstResponseSeconds(CarbonImmutable $rangeStart): ?float
    {
        $avg = Feedback::where('created_at', '>=', $rangeStart)
            ->whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, first_response_at)) as avg_seconds')
            ->value('avg_seconds');

        return $avg !== null ? (float) $avg : null;
    }

    /**
     * Mỗi dòng activity `to_value=resolved` là một điểm dữ liệu — góp ý mở lại
     * rồi giải quyết lại đóng góp 2 điểm, đều đo từ `created_at` gốc; góp ý
     * đóng mà chưa từng resolved không có dòng nào nên tự bị loại (mục 5.3).
     */
    private function avgResolutionSeconds(CarbonImmutable $rangeStart): ?float
    {
        $avg = $this->resolvedActivitiesQuery()
            ->where('feedback_activities.created_at', '>=', $rangeStart)
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, feedbacks.created_at, feedback_activities.created_at)) as avg_seconds')
            ->value('avg_seconds');

        return $avg !== null ? (float) $avg : null;
    }

    /**
     * @return Builder<FeedbackActivity>
     */
    private function resolvedActivitiesQuery(): Builder
    {
        return FeedbackActivity::query()
            ->join('feedbacks', 'feedbacks.id', '=', 'feedback_activities.feedback_id')
            ->where('feedback_activities.type', ActivityType::StatusChange->value)
            ->where('feedback_activities.to_value', FeedbackStatus::Resolved->value);
    }

    private function selectedWeeks(Request $request): int
    {
        $weeks = $request->integer('weeks', self::DEFAULT_WEEKS);

        return in_array($weeks, self::WEEK_OPTIONS, true) ? $weeks : self::DEFAULT_WEEKS;
    }

    private function rangeStart(int $weeks): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->subWeeks($weeks - 1);
    }
}
