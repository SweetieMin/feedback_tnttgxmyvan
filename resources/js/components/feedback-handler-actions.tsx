import { useForm } from '@inertiajs/react';
import { CheckCircle2, Hand, Play, UserRoundCog, XCircle } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import FeedbackAssignmentController from '@/actions/App/Http/Controllers/FeedbackAssignmentController';
import FeedbackStatusController from '@/actions/App/Http/Controllers/FeedbackStatusController';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { FeedbackDetail, FeedbackStatus, UserSummary } from '@/types';

const CLOSE_REASON_PRESETS = [
    'Trùng với một góp ý khác',
    'Ngoài phạm vi xử lý',
];

export function FeedbackHandlerActions({
    feedback,
    canSelfAssign,
    canReassign,
    transitions,
    handlers,
    conflict,
}: {
    feedback: FeedbackDetail;
    canSelfAssign: boolean;
    canReassign: boolean;
    transitions: FeedbackStatus[];
    handlers: UserSummary[];
    conflict?: string;
}) {
    const hasPublicReply = feedback.messages.some(
        (message) => message.visibility === 'public',
    );

    return (
        <>
            {canSelfAssign && <SelfAssignButton feedbackId={feedback.id} />}
            {transitions.includes('in_progress') && (
                <StartProgressButton feedbackId={feedback.id} />
            )}
            {transitions.includes('resolved') && (
                <ResolveDialog
                    feedbackId={feedback.id}
                    hasPublicReply={hasPublicReply}
                    conflict={conflict}
                />
            )}
            {canReassign && (
                <ReassignDialog
                    feedbackId={feedback.id}
                    currentAssigneeId={feedback.assigned_to}
                    handlers={handlers}
                    conflict={conflict}
                />
            )}
            {transitions.includes('closed') && (
                <CloseDialog feedbackId={feedback.id} conflict={conflict} />
            )}
        </>
    );
}

function SelfAssignButton({ feedbackId }: { feedbackId: number }) {
    const form = useForm({});

    return (
        <Button
            disabled={form.processing}
            onClick={() =>
                form.submit(
                    FeedbackAssignmentController.selfAssign(feedbackId),
                    {
                        preserveScroll: true,
                    },
                )
            }
        >
            {form.processing ? <Spinner /> : <Hand />}
            Nhận xử lý
        </Button>
    );
}

function StartProgressButton({ feedbackId }: { feedbackId: number }) {
    const form = useForm({ to_status: 'in_progress' });

    return (
        <Button
            disabled={form.processing}
            onClick={() =>
                form.submit(FeedbackStatusController.update(feedbackId), {
                    preserveScroll: true,
                })
            }
        >
            {form.processing ? <Spinner /> : <Play />}
            Bắt đầu xử lý
        </Button>
    );
}

function ActionDialog({
    trigger,
    title,
    description,
    submitLabel,
    submitVariant = 'default',
    processing,
    disabled = false,
    open,
    onOpenChange,
    onSubmit,
    children,
}: {
    trigger: ReactNode;
    title: string;
    description: ReactNode;
    submitLabel: string;
    submitVariant?: 'default' | 'destructive';
    processing: boolean;
    disabled?: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (event: FormEvent) => void;
    children?: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <form onSubmit={onSubmit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="ghost">
                                Huỷ
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={submitVariant}
                            disabled={processing || disabled}
                        >
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ResolveDialog({
    feedbackId,
    hasPublicReply,
    conflict,
}: {
    feedbackId: number;
    hasPublicReply: boolean;
    conflict?: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ to_status: 'resolved' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(FeedbackStatusController.update(feedbackId), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <ActionDialog
            trigger={
                <Button variant="outline">
                    <CheckCircle2 />
                    Đánh dấu đã giải quyết
                </Button>
            }
            title="Đánh dấu đã giải quyết"
            description={
                hasPublicReply
                    ? 'Người gửi sẽ nhận được thông báo và có thể mở lại nếu vấn đề chưa được giải quyết.'
                    : 'Cần có ít nhất một trả lời công khai trước khi giải quyết. Hãy gửi trả lời cho người gửi ở phần Trao đổi trước.'
            }
            submitLabel="Xác nhận đã giải quyết"
            processing={form.processing}
            disabled={!hasPublicReply}
            open={open}
            onOpenChange={setOpen}
            onSubmit={submit}
        >
            <InputError message={conflict} />
        </ActionDialog>
    );
}

function CloseDialog({
    feedbackId,
    conflict,
}: {
    feedbackId: number;
    conflict?: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ to_status: 'closed', reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(FeedbackStatusController.update(feedbackId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <ActionDialog
            trigger={
                <Button variant="outline">
                    <XCircle />
                    Đóng yêu cầu
                </Button>
            }
            title="Đóng yêu cầu"
            description="Góp ý đã đóng sẽ không được xử lý tiếp. Lý do đóng sẽ hiển thị cho người gửi."
            submitLabel="Đóng yêu cầu"
            submitVariant="destructive"
            processing={form.processing}
            open={open}
            onOpenChange={setOpen}
            onSubmit={submit}
        >
            <div className="grid gap-2">
                <Label htmlFor="close-reason">Lý do đóng</Label>
                <div className="flex flex-wrap gap-2">
                    {CLOSE_REASON_PRESETS.map((preset) => (
                        <Button
                            key={preset}
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={() => form.setData('reason', preset)}
                        >
                            {preset}
                        </Button>
                    ))}
                </div>
                <Textarea
                    id="close-reason"
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    maxLength={255}
                    aria-invalid={Boolean(form.errors.reason)}
                />
                <InputError message={form.errors.reason ?? conflict} />
            </div>
        </ActionDialog>
    );
}

function ReassignDialog({
    feedbackId,
    currentAssigneeId,
    handlers,
    conflict,
}: {
    feedbackId: number;
    currentAssigneeId: number | null;
    handlers: UserSummary[];
    conflict?: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ assigned_to: '' });
    const candidates = handlers.filter(
        (handler) => handler.id !== currentAssigneeId,
    );
    const label =
        currentAssigneeId === null
            ? 'Giao người phụ trách'
            : 'Đổi người phụ trách';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(FeedbackAssignmentController.reassign(feedbackId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <ActionDialog
            trigger={
                <Button variant="outline">
                    <UserRoundCog />
                    {label}
                </Button>
            }
            title={label}
            description="Người xử lý mới sẽ nhận được thông báo."
            submitLabel="Lưu"
            processing={form.processing}
            disabled={form.data.assigned_to === ''}
            open={open}
            onOpenChange={setOpen}
            onSubmit={submit}
        >
            <div className="grid gap-2">
                <Label htmlFor="assigned_to">Người xử lý</Label>
                {candidates.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Không có người xử lý nào khác để giao.
                    </p>
                ) : (
                    <Select
                        value={form.data.assigned_to}
                        onValueChange={(value) =>
                            form.setData('assigned_to', value)
                        }
                    >
                        <SelectTrigger id="assigned_to" className="w-full">
                            <SelectValue placeholder="Chọn người xử lý" />
                        </SelectTrigger>
                        <SelectContent>
                            {candidates.map((handler) => (
                                <SelectItem
                                    key={handler.id}
                                    value={String(handler.id)}
                                >
                                    {handler.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                <InputError message={form.errors.assigned_to ?? conflict} />
            </div>
        </ActionDialog>
    );
}
