import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, Inbox } from 'lucide-react';
import { useState } from 'react';
import FeedbackAssignmentController from '@/actions/App/Http/Controllers/FeedbackAssignmentController';
import { FeedbackStatusBadge } from '@/components/feedback-status-badge';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDateTime, STATUS_LABELS } from '@/lib/feedback';
import { index as adminFeedbackIndex } from '@/routes/admin/feedback';
import { show } from '@/routes/feedback';
import { index as queueIndex } from '@/routes/queue';
import type {
    Category,
    FeedbackStatus,
    QueueItem,
    UserRole,
    UserSummary,
} from '@/types';

type Filters = {
    status?: string;
    assignee?: string;
    category_id?: string;
};

type QueueTab = 'open' | 'resolved' | 'closed';

type QueueProps = {
    feedbacks: QueueItem[];
    categories: Category[];
    handlers: UserSummary[];
    filters: Filters;
    tab: QueueTab;
    tabCounts: Record<QueueTab, number>;
    scope: UserRole;
};

const ALL = 'all';

const TABS: { value: QueueTab; label: string; empty: string }[] = [
    {
        value: 'open',
        label: 'Chưa hoàn thành',
        empty: 'Không còn góp ý nào chưa hoàn thành.',
    },
    {
        value: 'resolved',
        label: 'Đã hoàn thành',
        empty: 'Chưa có góp ý nào đã hoàn thành.',
    },
    {
        value: 'closed',
        label: 'Đã đóng',
        empty: 'Chưa có góp ý nào đã đóng.',
    },
];

// Chỉ tab "Chưa hoàn thành" gồm nhiều trạng thái nên mới cần lọc tiếp theo
// trạng thái; hai tab còn lại mỗi tab đúng một trạng thái.
const OPEN_STATUSES: FeedbackStatus[] = ['new', 'reviewing', 'in_progress'];

export default function FeedbackQueue({
    feedbacks,
    categories,
    handlers,
    filters,
    tab,
    tabCounts,
    scope,
}: QueueProps) {
    const { url, props } = usePage();
    const isAdmin = scope === 'admin';
    const isFiltered = Boolean(
        filters.status || filters.assignee || filters.category_id,
    );
    const [assigningId, setAssigningId] = useState<number | null>(null);
    const activeTab = TABS.find((item) => item.value === tab) ?? TABS[0];

    const visit = (nextTab: QueueTab, nextFilters: Filters) => {
        router.get(
            url.split('?')[0],
            // Tab mặc định không cần nằm trên URL.
            nextTab === 'open' ? nextFilters : { ...nextFilters, tab: nextTab },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const applyFilter = (key: keyof Filters, value: string) => {
        const next: Filters = { ...filters, [key]: value };

        if (value === ALL) {
            delete next[key];
        }

        visit(tab, next);
    };

    const changeTab = (value: string) => {
        // Bộ lọc trạng thái chỉ thuộc về tab "Chưa hoàn thành" nên bỏ đi khi
        // đổi tab; người phụ trách và danh mục thì giữ nguyên.
        const next: Filters = { ...filters };
        delete next.status;

        visit(value as QueueTab, next);
    };

    const selfAssign = (feedbackId: number) => {
        setAssigningId(feedbackId);
        router.post(
            FeedbackAssignmentController.selfAssign.url(feedbackId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setAssigningId(null),
            },
        );
    };

    const title = isAdmin ? 'Tất cả góp ý' : 'Hàng đợi xử lý';

    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={title}
                    description={
                        isAdmin
                            ? 'Toàn bộ góp ý trong hệ thống. Mở từng góp ý để đổi người phụ trách hoặc trạng thái.'
                            : 'Nhận các góp ý mới chưa có người phụ trách và theo dõi góp ý bạn đang xử lý.'
                    }
                />

                {props.errors.conflict && (
                    <Alert variant="destructive">
                        <AlertCircle />
                        <AlertTitle>Không thực hiện được</AlertTitle>
                        <AlertDescription>
                            {props.errors.conflict}
                        </AlertDescription>
                    </Alert>
                )}

                <Tabs value={tab} onValueChange={changeTab} className="gap-6">
                    <div className="-mx-4 overflow-x-auto px-4">
                        {/* Trên điện thoại thu nhỏ chữ để cả ba tab vừa một
                            hàng; nếu vẫn tràn thì khối bọc ngoài cho cuộn ngang. */}
                        <TabsList className="w-max min-w-full sm:w-fit sm:min-w-0">
                            {TABS.map((item) => (
                                <TabsTrigger
                                    key={item.value}
                                    value={item.value}
                                    className="gap-1 px-1.5 text-xs sm:gap-1.5 sm:px-3 sm:text-sm"
                                >
                                    {item.label}
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {tabCounts[item.value]}
                                    </span>
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </div>

                    <TabsContent value={tab} className="flex flex-col gap-6">
                        <div className="flex flex-wrap gap-3">
                            {tab === 'open' && (
                                <Select
                                    value={filters.status ?? ALL}
                                    onValueChange={(value) =>
                                        applyFilter('status', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="w-44"
                                        aria-label="Lọc theo trạng thái"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            Mọi trạng thái
                                        </SelectItem>
                                        {OPEN_STATUSES.map((status) => (
                                            <SelectItem
                                                key={status}
                                                value={status}
                                            >
                                                {STATUS_LABELS[status]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}

                            <Select
                                value={filters.assignee ?? ALL}
                                onValueChange={(value) =>
                                    applyFilter('assignee', value)
                                }
                            >
                                <SelectTrigger
                                    className="w-52"
                                    aria-label="Lọc theo người phụ trách"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Mọi người phụ trách
                                    </SelectItem>
                                    <SelectItem value="unassigned">
                                        Chưa có người phụ trách
                                    </SelectItem>
                                    {!isAdmin && (
                                        <SelectItem value="me">
                                            Của tôi
                                        </SelectItem>
                                    )}
                                    {isAdmin &&
                                        handlers.map((handler) => (
                                            <SelectItem
                                                key={handler.id}
                                                value={String(handler.id)}
                                            >
                                                {handler.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={filters.category_id ?? ALL}
                                onValueChange={(value) =>
                                    applyFilter('category_id', value)
                                }
                            >
                                <SelectTrigger
                                    className="w-52"
                                    aria-label="Lọc theo danh mục"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Mọi danh mục
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <p className="text-sm text-muted-foreground">
                            {feedbacks.length} góp ý
                        </p>

                        {feedbacks.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                                <Inbox className="size-8 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">
                                    {isFiltered
                                        ? 'Không có góp ý nào khớp bộ lọc.'
                                        : activeTab.empty}
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y rounded-xl border">
                                {feedbacks.map((feedback) => {
                                    const canSelfAssign =
                                        scope === 'handler' &&
                                        feedback.status === 'new' &&
                                        feedback.assigned_to === null;

                                    return (
                                        <li
                                            key={feedback.id}
                                            className="flex flex-wrap items-center gap-3 px-4 py-3"
                                        >
                                            <Link
                                                href={show(feedback.id)}
                                                className="flex min-w-0 flex-1 flex-col gap-1.5"
                                            >
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium hover:underline">
                                                        {feedback.title}
                                                    </span>
                                                    <FeedbackStatusBadge
                                                        status={feedback.status}
                                                    />
                                                </div>
                                                {feedback.excerpt && (
                                                    <p className="line-clamp-1 text-sm text-muted-foreground">
                                                        {feedback.excerpt}
                                                    </p>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    {feedback.sender?.name ??
                                                        'Người dùng đã xoá'}{' '}
                                                    ·{' '}
                                                    {feedback.category?.name ??
                                                        '—'}{' '}
                                                    ·{' '}
                                                    {formatDateTime(
                                                        feedback.created_at,
                                                    )}{' '}
                                                    ·{' '}
                                                    {feedback.assignee
                                                        ? `Phụ trách: ${feedback.assignee.name}`
                                                        : 'Chưa có người phụ trách'}
                                                </p>
                                            </Link>
                                            {canSelfAssign && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        assigningId !== null
                                                    }
                                                    onClick={() =>
                                                        selfAssign(feedback.id)
                                                    }
                                                >
                                                    {assigningId ===
                                                        feedback.id && (
                                                        <Spinner />
                                                    )}
                                                    Nhận xử lý
                                                </Button>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

FeedbackQueue.layout = (props: QueueProps) => ({
    breadcrumbs: [
        props.scope === 'admin'
            ? { title: 'Tất cả góp ý', href: adminFeedbackIndex() }
            : { title: 'Hàng đợi xử lý', href: queueIndex() },
    ],
});
