import { Head, Link, router } from '@inertiajs/react';
import { Inbox, Plus } from 'lucide-react';
import { FeedbackStatusBadge } from '@/components/feedback-status-badge';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime, STATUS_LABELS, STATUS_ORDER } from '@/lib/feedback';
import { create, index, show } from '@/routes/feedback';
import type { Category, FeedbackListItem } from '@/types';

type Filters = {
    status?: string;
    category_id?: string;
};

const ALL = 'all';

export default function FeedbackIndex({
    feedbacks,
    categories,
    filters,
}: {
    feedbacks: FeedbackListItem[];
    categories: Category[];
    filters: Filters;
}) {
    const isFiltered = Boolean(filters.status || filters.category_id);

    const applyFilter = (key: keyof Filters, value: string) => {
        const next: Filters = { ...filters, [key]: value };

        if (value === ALL) {
            delete next[key];
        }

        router.get(index.url(), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Góp ý của tôi" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Góp ý của tôi"
                        description="Theo dõi các góp ý bạn đã gửi và tiến độ xử lý."
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            Gửi góp ý
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) => applyFilter('status', value)}
                    >
                        <SelectTrigger
                            className="w-48"
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
                        value={filters.category_id ?? ALL}
                        onValueChange={(value) =>
                            applyFilter('category_id', value)
                        }
                    >
                        <SelectTrigger
                            className="w-56"
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

                {feedbacks.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                        <Inbox className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            {isFiltered
                                ? 'Không có góp ý nào khớp bộ lọc.'
                                : 'Bạn chưa gửi góp ý nào.'}
                        </p>
                        {!isFiltered && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={create()}>Gửi góp ý đầu tiên</Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {feedbacks.map((feedback) => (
                            <li key={feedback.id}>
                                <Link
                                    href={show(feedback.id)}
                                    className="flex flex-col gap-1.5 px-4 py-3 transition-colors hover:bg-muted/50"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {feedback.title}
                                        </span>
                                        <FeedbackStatusBadge
                                            status={feedback.status}
                                        />
                                    </div>
                                    {feedback.excerpt && (
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {feedback.excerpt}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        {feedback.category?.name ??
                                            'Không rõ danh mục'}{' '}
                                        · Gửi lúc{' '}
                                        {formatDateTime(feedback.created_at)}
                                    </p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

FeedbackIndex.layout = {
    breadcrumbs: [
        {
            title: 'Góp ý của tôi',
            href: index(),
        },
    ],
};
