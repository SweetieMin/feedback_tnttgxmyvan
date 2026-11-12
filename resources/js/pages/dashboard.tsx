import { Head, Link, router } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ChevronRight, Clock, Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { FeedbackStatusBadge } from '@/components/feedback-status-badge';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDateTime, STATUS_LABELS, STATUS_ORDER } from '@/lib/feedback';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as adminFeedbackIndex } from '@/routes/admin/feedback';
import { create, index as feedbackIndex, show } from '@/routes/feedback';
import { index as queueIndex } from '@/routes/queue';
import type { FeedbackStatus } from '@/types';

type Href = NonNullable<InertiaLinkProps['href']>;
type StatusCounts = Record<FeedbackStatus, number>;

type EmployeeProps = {
    scope: 'employee';
    statusCounts: StatusCounts;
    recentFeedbacks: {
        id: number;
        title: string;
        status: FeedbackStatus;
        created_at: string;
    }[];
};

type HandlerProps = {
    scope: 'handler';
    newCount: number;
    unassignedCount: number;
    longestOpen: { id: number; title: string; daysOpen: number }[];
    statusCounts: StatusCounts;
};

type WeekPoint = {
    week: string;
    weekStart: string;
    received: number;
    resolved: number;
};

type WorkloadRow = {
    id: number;
    name: string;
    inProgress: number;
    resolved30d: number;
};

type AdminProps = {
    scope: 'admin';
    weeks: number;
    weekOptions: number[];
    statusCounts: StatusCounts;
    workloadByAssignee: WorkloadRow[];
    weeklyTrend: WeekPoint[];
    avgFirstResponseSeconds: number | null;
    avgResolutionSeconds: number | null;
};

type DashboardProps = EmployeeProps | HandlerProps | AdminProps;

export default function Dashboard(props: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-8 p-4">
                {props.scope === 'employee' && <EmployeeDashboard {...props} />}
                {props.scope === 'handler' && <HandlerDashboard {...props} />}
                {props.scope === 'admin' && <AdminDashboard {...props} />}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

function EmployeeDashboard({ statusCounts, recentFeedbacks }: EmployeeProps) {
    return (
        <>
            <Heading
                title="Góp ý của tôi"
                description="Tình trạng các góp ý bạn đã gửi."
            />

            <StatusTiles
                counts={statusCounts}
                hrefFor={(status) => feedbackIndex({ query: { status } })}
            />

            <Section title="Gửi gần đây">
                {recentFeedbacks.length === 0 ? (
                    <EmptyState>
                        Bạn chưa gửi góp ý nào.{' '}
                        <Link
                            href={create()}
                            className="underline underline-offset-4"
                        >
                            Gửi góp ý
                        </Link>
                    </EmptyState>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {recentFeedbacks.map((feedback) => (
                            <li key={feedback.id}>
                                <RowLink href={show(feedback.id)}>
                                    <span className="min-w-0 flex-1 truncate font-medium">
                                        {feedback.title}
                                    </span>
                                    <FeedbackStatusBadge
                                        status={feedback.status}
                                    />
                                    <span className="hidden text-xs text-muted-foreground sm:inline">
                                        {formatDateTime(feedback.created_at)}
                                    </span>
                                </RowLink>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>
        </>
    );
}

function HandlerDashboard({
    newCount,
    unassignedCount,
    longestOpen,
    statusCounts,
}: HandlerProps) {
    return (
        <>
            <Heading
                title="Hàng đợi xử lý"
                description="Số liệu tại thời điểm hiện tại của toàn bộ hàng đợi."
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <StatTile
                    label="Góp ý mới"
                    value={newCount}
                    href={queueIndex({ query: { status: 'new' } })}
                    emphasis
                />
                <StatTile
                    label="Chưa được giao"
                    value={unassignedCount}
                    hint="Chưa có người phụ trách, chưa giải quyết/đóng"
                    href={queueIndex({ query: { assignee: 'unassigned' } })}
                    emphasis
                />
            </div>

            <Section title="Theo trạng thái">
                <StatusTiles
                    counts={statusCounts}
                    hrefFor={(status) => queueIndex({ query: { status } })}
                />
            </Section>

            <Section title="Đang mở lâu nhất">
                {longestOpen.length === 0 ? (
                    <EmptyState>Không có góp ý đang mở.</EmptyState>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {longestOpen.map((feedback) => (
                            <li key={feedback.id}>
                                <RowLink href={show(feedback.id)}>
                                    <span className="min-w-0 flex-1 truncate font-medium">
                                        {feedback.title}
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1 text-sm text-muted-foreground tabular-nums">
                                        <Clock className="size-3.5" />
                                        {feedback.daysOpen === 0
                                            ? 'Mở hôm nay'
                                            : `Mở ${feedback.daysOpen} ngày`}
                                    </span>
                                </RowLink>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>
        </>
    );
}

function AdminDashboard({
    weeks,
    weekOptions,
    statusCounts,
    workloadByAssignee,
    weeklyTrend,
    avgFirstResponseSeconds,
    avgResolutionSeconds,
}: AdminProps) {
    const changeWeeks = (value: string) => {
        router.get(
            dashboard.url(),
            { weeks: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Heading
                title="Tổng quan hệ thống"
                description="Số liệu trạng thái tính tại thời điểm hiện tại; xu hướng và thời gian xử lý tính theo khoảng tuần đang chọn."
            />

            <Section title="Theo trạng thái">
                <StatusTiles
                    counts={statusCounts}
                    hrefFor={(status) =>
                        adminFeedbackIndex({ query: { status } })
                    }
                />
            </Section>

            <section className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-base font-medium">
                        Xu hướng & thời gian xử lý
                    </h2>
                    <Select value={String(weeks)} onValueChange={changeWeeks}>
                        <SelectTrigger
                            className="w-44"
                            aria-label="Khoảng thời gian"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {weekOptions.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    {option} tuần gần nhất
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <DurationTile
                        label="Thời gian đến phản hồi đầu tiên"
                        hint="Trung bình trên các góp ý đã có phản hồi"
                        seconds={avgFirstResponseSeconds}
                    />
                    <DurationTile
                        label="Thời gian đến giải quyết"
                        hint="Mỗi lần giải quyết tính từ lúc gửi góp ý"
                        seconds={avgResolutionSeconds}
                    />
                </div>

                <WeeklyTrendCard data={weeklyTrend} weeks={weeks} />
            </section>

            <Section title="Khối lượng theo người phụ trách">
                {workloadByAssignee.length === 0 ? (
                    <EmptyState>Chưa có người xử lý nào.</EmptyState>
                ) : (
                    <div className="rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">
                                        Người xử lý
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Đang xử lý
                                    </TableHead>
                                    <TableHead className="pr-4 text-right">
                                        Đã giải quyết (30 ngày)
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {workloadByAssignee.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="pl-4">
                                            <Link
                                                href={adminFeedbackIndex({
                                                    query: {
                                                        assignee: row.id,
                                                    },
                                                })}
                                                className="font-medium hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.inProgress}
                                        </TableCell>
                                        <TableCell className="pr-4 text-right tabular-nums">
                                            {row.resolved30d}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </Section>
        </>
    );
}

const trendConfig = {
    received: {
        label: 'Nhận',
        theme: { light: '#2a78d6', dark: '#3987e5' },
    },
    resolved: {
        label: 'Giải quyết',
        theme: { light: '#eb6834', dark: '#d95926' },
    },
} satisfies ChartConfig;

function shortDate(isoDate: string): string {
    const [, month, day] = isoDate.split('-');

    return `${day}/${month}`;
}

function WeeklyTrendCard({
    data,
    weeks,
}: {
    data: WeekPoint[];
    weeks: number;
}) {
    const isEmpty = data.every(
        (point) => point.received === 0 && point.resolved === 0,
    );

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="text-base">
                    Nhận và giải quyết theo tuần
                </CardTitle>
                <p className="text-sm text-muted-foreground">
                    {weeks} tuần gần nhất, mỗi cột là một tuần (thứ Hai – Chủ
                    nhật). Một góp ý mở lại rồi giải quyết lại được tính vào
                    đúng tuần của từng lần giải quyết.
                </p>
            </CardHeader>
            <CardContent>
                {isEmpty ? (
                    <EmptyState>
                        Chưa có dữ liệu trong khoảng thời gian này.
                    </EmptyState>
                ) : (
                    <Tabs defaultValue="chart">
                        <TabsList>
                            <TabsTrigger value="chart">Biểu đồ</TabsTrigger>
                            <TabsTrigger value="table">Bảng</TabsTrigger>
                        </TabsList>
                        <TabsContent value="chart">
                            <ChartContainer
                                config={trendConfig}
                                className="aspect-auto h-64 w-full"
                            >
                                <BarChart
                                    data={data}
                                    barGap={2}
                                    margin={{ left: -16, right: 8, top: 8 }}
                                >
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="weekStart"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        tickFormatter={shortDate}
                                    />
                                    <YAxis
                                        allowDecimals={false}
                                        tickLine={false}
                                        axisLine={false}
                                        width={40}
                                    />
                                    <ChartTooltip
                                        cursor={{ fillOpacity: 0.4 }}
                                        content={
                                            <ChartTooltipContent
                                                labelFormatter={(
                                                    _,
                                                    payload,
                                                ) => {
                                                    const point = payload?.[0]
                                                        ?.payload as
                                                        | WeekPoint
                                                        | undefined;

                                                    return point
                                                        ? `Tuần ${point.week.split('-W')[1]} · từ ${shortDate(point.weekStart)}`
                                                        : '';
                                                }}
                                            />
                                        }
                                    />
                                    <ChartLegend
                                        content={<ChartLegendContent />}
                                    />
                                    <Bar
                                        dataKey="received"
                                        fill="var(--color-received)"
                                        radius={[4, 4, 0, 0]}
                                        maxBarSize={28}
                                    />
                                    <Bar
                                        dataKey="resolved"
                                        fill="var(--color-resolved)"
                                        radius={[4, 4, 0, 0]}
                                        maxBarSize={28}
                                    />
                                </BarChart>
                            </ChartContainer>
                        </TabsContent>
                        <TabsContent value="table">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tuần</TableHead>
                                        <TableHead className="text-right">
                                            Nhận
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Giải quyết
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.map((point) => (
                                        <TableRow key={point.week}>
                                            <TableCell>
                                                {point.week} (từ{' '}
                                                {shortDate(point.weekStart)})
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {point.received}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {point.resolved}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </TabsContent>
                    </Tabs>
                )}
            </CardContent>
        </Card>
    );
}

function formatDuration(totalSeconds: number): string {
    const minutes = Math.round(totalSeconds / 60);

    if (minutes < 60) {
        return `${Math.max(minutes, 1)} phút`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        const rest = minutes % 60;

        return rest > 0 ? `${hours} giờ ${rest} phút` : `${hours} giờ`;
    }

    const days = Math.floor(hours / 24);
    const restHours = hours % 24;

    return restHours > 0 ? `${days} ngày ${restHours} giờ` : `${days} ngày`;
}

function DurationTile({
    label,
    hint,
    seconds,
}: {
    label: string;
    hint: string;
    seconds: number | null;
}) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            {seconds === null ? (
                <p className="mt-2 text-lg font-medium text-muted-foreground">
                    Chưa có dữ liệu
                </p>
            ) : (
                <p className="mt-1 text-2xl font-semibold tracking-tight">
                    {formatDuration(seconds)}
                </p>
            )}
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
        </div>
    );
}

function StatTile({
    label,
    value,
    href,
    hint,
    emphasis = false,
}: {
    label: string;
    value: number;
    href: Href;
    hint?: string;
    emphasis?: boolean;
}) {
    return (
        <Link
            href={href}
            className="group flex flex-col rounded-xl border p-4 transition-colors hover:bg-muted/50"
        >
            <span className="flex items-center justify-between text-sm text-muted-foreground">
                {label}
                <ChevronRight className="size-4 opacity-0 transition-opacity group-hover:opacity-100" />
            </span>
            <span
                className={cn(
                    'mt-1 font-semibold tracking-tight tabular-nums',
                    emphasis ? 'text-3xl' : 'text-2xl',
                )}
            >
                {value}
            </span>
            {hint && (
                <span className="mt-1 text-xs text-muted-foreground">
                    {hint}
                </span>
            )}
        </Link>
    );
}

function StatusTiles({
    counts,
    hrefFor,
}: {
    counts: StatusCounts;
    hrefFor: (status: FeedbackStatus) => Href;
}) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {STATUS_ORDER.map((status) => (
                <StatTile
                    key={status}
                    label={STATUS_LABELS[status]}
                    value={counts[status]}
                    href={hrefFor(status)}
                />
            ))}
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-4">
            <h2 className="text-base font-medium">{title}</h2>
            {children}
        </section>
    );
}

function RowLink({ href, children }: { href: Href; children: ReactNode }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50"
        >
            {children}
        </Link>
    );
}

function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
            <Inbox className="size-6" />
            <p>{children}</p>
        </div>
    );
}
