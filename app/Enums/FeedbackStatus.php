<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case New = 'new';
    case Reviewing = 'reviewing';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
