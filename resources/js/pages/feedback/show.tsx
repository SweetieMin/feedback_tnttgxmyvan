import { Head, useForm, usePage } from '@inertiajs/react';
import type { JSONContent } from '@tiptap/react';
import {
    AlertCircle,
    GitCommitHorizontal,
    Lock,
    Pencil,
    RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import FeedbackController from '@/actions/App/Http/Controllers/FeedbackController';
import FeedbackMessageController from '@/actions/App/Http/Controllers/FeedbackMessageController';
import FeedbackStatusController from '@/actions/App/Http/Controllers/FeedbackStatusController';
import { FeedbackForm } from '@/components/feedback-form';
import { FeedbackHandlerActions } from '@/components/feedback-handler-actions';
import { FeedbackStatusBadge } from '@/components/feedback-status-badge';
import InputError from '@/components/input-error';
import { TiptapEditor } from '@/components/tiptap-editor';
import { TiptapViewer } from '@/components/tiptap-viewer';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useInitials } from '@/hooks/use-initials';
import { describeActivity, formatDateTime } from '@/lib/feedback';
import { hasTextContent } from '@/lib/tiptap';
import { cn } from '@/lib/utils';
import { index as adminFeedbackIndex } from '@/routes/admin/feedback';
import { index, show } from '@/routes/feedback';
import { index as queueIndex } from '@/routes/queue';
import type {
    Auth,
    Category,
    FeedbackActivity,
    FeedbackDetail,
    FeedbackMessage,
    FeedbackStatus,
    MessageVisibility,
    UserSummary,
} from '@/types';

type Can = {
    update: boolean;
    reopen: boolean;
    selfAssign: boolean;
    changeStatus: boolean;
    reassign: boolean;
    addInternalNote: boolean;
};

type ShowProps = {
    feedback: FeedbackDetail;
    can: Can;
    transitions: FeedbackStatus[];
    categories: Category[];
    handlers: UserSummary[];
};

export default function FeedbackShow({
    feedback,
    can,
    transitions,
    categories,
    handlers,
}: ShowProps) {
    const { errors } = usePage().props;
    const [editing, setEditing] = useState(false);

    const senderName = feedback.sender?.name ?? 'Người dùng đã xoá';

    return (
        <>
            <Head title={feedback.title} />

            <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <header className="border-b pb-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <h1 className="min-w-0 flex-1 basis-64 text-2xl font-semibold tracking-tight wrap-break-word sm:text-3xl">
                            {feedback.title}{' '}
                            <span className="font-normal text-muted-foreground">
                                #{feedback.id}
                            </span>
                        </h1>

                        <div className="flex flex-wrap gap-2">
                            <FeedbackHandlerActions
                                feedback={feedback}
                                canSelfAssign={can.selfAssign}
                                canReassign={can.reassign}
                                transitions={transitions}
                                handlers={handlers}
                                conflict={errors.conflict}
                            />
                            {can.update && !editing && (
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                >
                                    <Pencil />
                                    Sửa góp ý
                                </Button>
                            )}
                            {can.reopen && (
                                <ReopenDialog
                                    feedbackId={feedback.id}
                                    conflict={errors.conflict}
                                />
                            )}
                        </div>
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-muted-foreground">
                        <FeedbackStatusBadge
                            status={feedback.status}
                            className="rounded-full px-3 py-1"
                        />
                        <span>
                            <strong className="font-semibold text-foreground">
                                {senderName}
                            </strong>
                            {' đã gửi vào '}
                            <time dateTime={feedback.created_at}>
                                {formatDateTime(feedback.created_at)}
                            </time>
                        </span>
                        {feedback.reopened_count > 0 && (
                            <span>Đã mở lại {feedback.reopened_count} lần</span>
                        )}
                    </div>
                </header>

                {errors.conflict && (
                    <Alert variant="destructive" className="mt-5">
                        <AlertCircle />
                        <AlertTitle>Không thực hiện được</AlertTitle>
                        <AlertDescription>{errors.conflict}</AlertDescription>
                    </Alert>
                )}

                <div className="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_15rem]">
                    <section
                        aria-label="Nội dung và trao đổi"
                        className="min-w-0"
                    >
                        {editing ? (
                            <div className="rounded-md border p-4 sm:ml-13">
                                <FeedbackForm
                                    action={FeedbackController.update(
                                        feedback.id,
                                    )}
                                    categories={categories}
                                    submitLabel="Lưu thay đổi"
                                    initial={{
                                        title: feedback.title,
                                        category_id: feedback.category_id,
                                        content: feedback.content,
                                    }}
                                    onCancel={() => setEditing(false)}
                                    onSuccess={() => setEditing(false)}
                                />
                            </div>
                        ) : (
                            <CommentCard
                                author={senderName}
                                authorAvatar={feedback.sender?.avatar}
                                date={feedback.created_at}
                                body={feedback.content}
                                opening
                                badge={
                                    <Badge variant="outline">Người gửi</Badge>
                                }
                            />
                        )}

                        <DiscussionTimeline
                            messages={feedback.messages}
                            activities={feedback.activities}
                            ownerId={feedback.user_id}
                        />

                        {feedback.status === 'closed' &&
                            feedback.closed_reason && (
                                <Alert className="mt-4 sm:ml-13">
                                    <Lock />
                                    <AlertTitle>Góp ý đã được đóng</AlertTitle>
                                    <AlertDescription>
                                        {feedback.closed_reason}
                                    </AlertDescription>
                                </Alert>
                            )}

                        <ReplyForm
                            feedbackId={feedback.id}
                            allowInternal={can.addInternalNote}
                        />
                    </section>

                    <aside aria-label="Thông tin góp ý" className="min-w-0">
                        <dl className="divide-y border-y text-sm lg:border-t-0">
                            <InfoRow label="Người phụ trách">
                                {feedback.assignee ? (
                                    <span className="flex items-center gap-2 font-medium text-foreground">
                                        <PersonAvatar
                                            name={feedback.assignee.name}
                                            avatar={feedback.assignee.avatar}
                                            compact
                                        />
                                        {feedback.assignee.name}
                                    </span>
                                ) : (
                                    'Chưa có người phụ trách'
                                )}
                            </InfoRow>
                            <InfoRow label="Danh mục">
                                {feedback.category ? (
                                    <Badge
                                        variant="secondary"
                                        className="h-auto max-w-full rounded-full whitespace-normal"
                                    >
                                        {feedback.category.name}
                                    </Badge>
                                ) : (
                                    'Chưa có danh mục'
                                )}
                            </InfoRow>
                            {feedback.first_response_at && (
                                <InfoRow label="Phản hồi đầu tiên">
                                    {formatDateTime(feedback.first_response_at)}
                                </InfoRow>
                            )}
                            {feedback.resolved_at && (
                                <InfoRow label="Giải quyết lúc">
                                    {formatDateTime(feedback.resolved_at)}
                                </InfoRow>
                            )}
                            {feedback.closed_at && (
                                <InfoRow label="Đóng lúc">
                                    {formatDateTime(feedback.closed_at)}
                                </InfoRow>
                            )}
                        </dl>
                    </aside>
                </div>
            </div>
        </>
    );
}

FeedbackShow.layout = (props: ShowProps & { auth: Auth }) => {
    const { user } = props.auth;
    let parent = { title: 'Góp ý của tôi', href: index() };

    if (props.feedback.user_id !== user.id && user.role === 'admin') {
        parent = { title: 'Tất cả góp ý', href: adminFeedbackIndex() };
    } else if (props.feedback.user_id !== user.id) {
        parent = { title: 'Hàng đợi xử lý', href: queueIndex() };
    }

    return {
        breadcrumbs: [
            parent,
            { title: props.feedback.title, href: show(props.feedback.id) },
        ],
    };
};

function InfoRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-2 py-4 lg:first:pt-0">
            <dt className="text-xs font-semibold text-foreground">{label}</dt>
            <dd className="wrap-break-word text-muted-foreground">
                {children}
            </dd>
        </div>
    );
}

function PersonAvatar({
    name,
    avatar,
    compact = false,
}: {
    name: string;
    avatar?: string | null;
    compact?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <Avatar aria-hidden="true" className={compact ? 'size-6' : 'size-9'}>
            {avatar && <AvatarImage src={avatar} alt={name} />}
            <AvatarFallback className="border bg-muted text-xs font-semibold">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}

function CommentCard({
    author,
    authorAvatar,
    date,
    body,
    badge,
    opening = false,
    internal = false,
}: {
    author: string;
    authorAvatar?: string | null;
    date: string;
    body: JSONContent;
    badge?: ReactNode;
    opening?: boolean;
    internal?: boolean;
}) {
    return (
        <article className="relative sm:pl-13">
            <div className="absolute top-0 left-0 hidden sm:block">
                <PersonAvatar name={author} avatar={authorAvatar} />
            </div>
            <div
                className={cn(
                    'relative min-w-0 rounded-md border bg-background',
                    opening && 'border-blue-200 dark:border-blue-900',
                    internal && 'border-amber-200 dark:border-amber-900',
                )}
            >
                <span
                    aria-hidden="true"
                    className={cn(
                        'absolute top-4 -left-1.5 hidden size-2.5 rotate-45 border-b border-l bg-muted sm:block',
                        opening &&
                            'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950',
                        internal &&
                            'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
                    )}
                />
                <header
                    className={cn(
                        'flex flex-wrap items-center justify-between gap-x-3 gap-y-1 rounded-t-md border-b bg-muted px-4 py-2.5 text-sm',
                        opening &&
                            'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950',
                        internal &&
                            'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
                    )}
                >
                    <p className="min-w-0 wrap-break-word text-muted-foreground">
                        <strong className="font-semibold text-foreground">
                            {author}
                        </strong>
                        {' · '}
                        <time dateTime={date}>{formatDateTime(date)}</time>
                    </p>
                    {badge}
                </header>
                <TiptapViewer
                    className="overflow-x-auto px-4 py-4 sm:px-5"
                    content={body}
                />
            </div>
        </article>
    );
}

function DiscussionTimeline({
    messages,
    activities,
    ownerId,
}: {
    messages: FeedbackMessage[];
    activities: FeedbackActivity[];
    ownerId: number;
}) {
    const { auth } = usePage().props;
    const entries = [
        ...messages.map((message) => ({
            kind: 'message' as const,
            ...message,
        })),
        ...activities
            .filter(
                (activity) =>
                    activity.type !== 'status_change' ||
                    activity.from_value !== null,
            )
            .map((activity) => ({ kind: 'activity' as const, ...activity })),
    ].sort((a, b) => Date.parse(a.created_at) - Date.parse(b.created_at));

    if (entries.length === 0) {
        return null;
    }

    return (
        <ol className="relative space-y-4 pt-4 before:absolute before:inset-y-0 before:left-4.5 before:w-px before:bg-border before:content-[''] before:max-sm:hidden">
            {entries.map((entry) => {
                if (entry.kind === 'activity') {
                    return (
                        <li
                            key={`activity-${entry.id}`}
                            className="relative flex items-start gap-3 py-1 sm:pl-13"
                        >
                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full border bg-muted text-muted-foreground sm:absolute sm:top-1 sm:left-1">
                                <GitCommitHorizontal
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </span>
                            <div className="min-w-0 pt-1 text-sm wrap-break-word text-muted-foreground">
                                <strong className="font-semibold text-foreground">
                                    {entry.actor?.name ?? 'Hệ thống'}
                                </strong>{' '}
                                {describeActivity(entry)}
                                {' · '}
                                <time dateTime={entry.created_at}>
                                    {formatDateTime(entry.created_at)}
                                </time>
                                {entry.note && (
                                    <p className="mt-1">{entry.note}</p>
                                )}
                            </div>
                        </li>
                    );
                }

                const internal = entry.visibility === 'internal';
                const isSelf = entry.author_id === auth.user.id;
                const author = isSelf
                    ? 'Bạn'
                    : (entry.author?.name ?? 'Người dùng đã xoá');
                const authorAvatar = isSelf
                    ? auth.user.avatar
                    : entry.author?.avatar;

                return (
                    <li key={`message-${entry.id}`}>
                        <CommentCard
                            author={author}
                            authorAvatar={authorAvatar}
                            date={entry.created_at}
                            body={entry.body}
                            internal={internal}
                            badge={
                                internal ? (
                                    <Badge variant="outline">
                                        <Lock />
                                        Ghi chú nội bộ
                                    </Badge>
                                ) : entry.author_id !== ownerId ? (
                                    <Badge variant="outline">Đội xử lý</Badge>
                                ) : undefined
                            }
                        />
                    </li>
                );
            })}
        </ol>
    );
}

function ReplyForm({
    feedbackId,
    allowInternal,
}: {
    feedbackId: number;
    allowInternal: boolean;
}) {
    const { auth } = usePage().props;
    const form = useForm<{
        body: JSONContent | null;
        visibility: MessageVisibility;
    }>({ body: null, visibility: 'public' });
    const [editorKey, setEditorKey] = useState(0);
    const isInternal = form.data.visibility === 'internal';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            body: hasTextContent(data.body) ? data.body : null,
            visibility: data.visibility,
        }));
        form.submit(FeedbackMessageController.store(feedbackId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body');
                setEditorKey((key) => key + 1);
            },
        });
    };

    return (
        <form onSubmit={submit} className="relative mt-6 sm:pl-13">
            <div className="absolute top-0 left-0 hidden sm:block">
                <PersonAvatar name={auth.user.name} avatar={auth.user.avatar} />
            </div>
            <div className="space-y-3">
                <h2 className="text-base font-semibold">Thêm phản hồi</h2>
                {allowInternal && (
                    <div className="flex flex-wrap items-center gap-3">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            value={form.data.visibility}
                            onValueChange={(value) => {
                                if (value) {
                                    form.setData(
                                        'visibility',
                                        value as MessageVisibility,
                                    );
                                }
                            }}
                            aria-label="Loại trao đổi"
                        >
                            <ToggleGroupItem value="public" className="px-3">
                                Trả lời công khai
                            </ToggleGroupItem>
                            <ToggleGroupItem value="internal" className="px-3">
                                Ghi chú nội bộ
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <span className="text-xs text-muted-foreground">
                            {isInternal
                                ? 'Chỉ người xử lý và quản trị viên thấy.'
                                : 'Người gửi sẽ thấy và nhận được thông báo.'}
                        </span>
                    </div>
                )}
                <Label className="sr-only">Nội dung trao đổi</Label>
                <TiptapEditor
                    key={editorKey}
                    profile="message"
                    value={form.data.body}
                    onChange={(value) => form.setData('body', value)}
                />
                <InputError message={form.errors.body} />
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={
                            form.processing || !hasTextContent(form.data.body)
                        }
                        className="bg-green-700 text-white hover:bg-green-800 dark:bg-green-800 dark:hover:bg-green-700"
                    >
                        {form.processing && <Spinner />}
                        {isInternal ? 'Lưu ghi chú nội bộ' : 'Gửi phản hồi'}
                    </Button>
                </div>
            </div>
        </form>
    );
}

function ReopenDialog({
    feedbackId,
    conflict,
}: {
    feedbackId: number;
    conflict?: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(FeedbackStatusController.reopen(feedbackId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <RotateCcw />
                    Mở lại yêu cầu
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Mở lại yêu cầu</DialogTitle>
                        <DialogDescription>
                            Cho đội xử lý biết vì sao vấn đề vẫn chưa được giải
                            quyết.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="reason">Lý do</Label>
                        <Textarea
                            id="reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            maxLength={255}
                            aria-invalid={Boolean(form.errors.reason)}
                        />
                        <InputError message={form.errors.reason ?? conflict} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="ghost">
                                Huỷ
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Mở lại
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
