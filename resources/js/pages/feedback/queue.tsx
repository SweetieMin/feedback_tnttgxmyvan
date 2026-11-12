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
import { formatDateTime, STATUS_LABELS, STATUS_ORDER } from '@/lib/feedback';
import { index as adminFeedbackIndex } from '@/routes/admin/feedback';
import { show } from '@/routes/feedback';
import { index as queueIndex } from '@/routes/queue';
import type { Category, QueueItem, UserRole, UserSummary } from '@/types';

type Filters = {
    status?: string;
    assignee?: string;
    category_id?: string;
};

type QueueProps = {
    feedbacks: QueueItem[];
    categories: Category[];
    handlers: UserSummary[];
    filters: Filters;
    scope: UserRole;
};

const ALL = 'all';

export default function FeedbackQueue({
    feedbacks,
    categories,
    handlers,
    filters,
    scope,
}: QueueProps) {
    const { url, props } = usePage();
    const isAdmin = scope === 'admin';
    const isFiltered = Boolean(
        filters.status || filters.assignee || filters.category_id,
    );
    const [assigningId, setAssigningId] = useState<number | null>(null);

    const applyFilter = (key: keyof Filters, value: string) => {
        const next: Filters = { ...filters, [key]: value };

        if (value === ALL) {
            delete next[key];
        }

        router.get(url.split('?')[0], next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
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

                <div className="flex flex-wrap gap-3">
                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) => applyFilter('status', value)}
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label="Lọc theo trạng thái"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Mọi trạng thái</SelectItem>
                            {STATUS_ORDER.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {STATUS_LABELS[status]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

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
                                <SelectItem value="me">Của tôi</SelectItem>
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
                            <SelectItem value={ALL}>Mọi danh mục</SelectItem>
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
                                : 'Hàng đợi đang trống.'}
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
                                            · {feedback.category?.name ?? '—'} ·{' '}
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
                                            disabled={assigningId !== null}
                                            onClick={() =>
                                                selfAssign(feedback.id)
                                            }
                                        >
                                            {assigningId === feedback.id && (
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
