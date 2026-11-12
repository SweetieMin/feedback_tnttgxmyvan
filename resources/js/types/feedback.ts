import type { JSONContent } from '@tiptap/react';

export type FeedbackStatus =
    | 'new'
    | 'reviewing'
    | 'in_progress'
    | 'resolved'
    | 'closed';

export type MessageVisibility = 'public' | 'internal';

export type ActivityType = 'status_change' | 'assignment_change' | 'reopened';

export type UserSummary = {
    id: number;
    name: string;
};

export type Category = {
    id: number;
    name: string;
};

export type FeedbackListItem = {
    id: number;
    title: string;
    excerpt: string | null;
    status: FeedbackStatus;
    category_id: number;
    assigned_to: number | null;
    created_at: string;
    category: Category | null;
};

export type QueueItem = FeedbackListItem & {
    user_id: number;
    sender: UserSummary | null;
    assignee: UserSummary | null;
};

export type FeedbackActivity = {
    id: number;
    actor_id: number;
    type: ActivityType;
    from_value: string | null;
    to_value: string | null;
    note: string | null;
    created_at: string;
    actor: UserSummary | null;
};

export type FeedbackMessage = {
    id: number;
    author_id: number;
    body: JSONContent;
    visibility: MessageVisibility;
    created_at: string;
    author: UserSummary | null;
};

export type NotificationType =
    | 'assigned'
    | 'new_reply'
    | 'status_changed'
    | 'reopened';

export type FeedbackNotification = {
    id: number;
    feedback_id: number | null;
    type: NotificationType;
    data: { title?: string; to_status?: FeedbackStatus };
    read_at: string | null;
    created_at: string;
    feedback: { id: number; title: string } | null;
};

export type FeedbackDetail = {
    id: number;
    user_id: number;
    category_id: number;
    assigned_to: number | null;
    title: string;
    content: JSONContent;
    status: FeedbackStatus;
    closed_reason: string | null;
    first_response_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    reopened_count: number;
    created_at: string;
    category: Category | null;
    sender: UserSummary | null;
    assignee: UserSummary | null;
    activities: FeedbackActivity[];
    messages: FeedbackMessage[];
};
