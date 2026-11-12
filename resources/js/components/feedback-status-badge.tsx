import { Badge } from '@/components/ui/badge';
import { STATUS_BADGE_CLASSES, STATUS_LABELS } from '@/lib/feedback';
import { cn } from '@/lib/utils';
import type { FeedbackStatus } from '@/types';

export function FeedbackStatusBadge({
    status,
    className,
}: {
    status: FeedbackStatus;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(STATUS_BADGE_CLASSES[status], className)}
        >
            {STATUS_LABELS[status]}
        </Badge>
    );
}
