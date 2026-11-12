<?php

namespace App\Enums;

enum ActivityType: string
{
    case StatusChange = 'status_change';
    case AssignmentChange = 'assignment_change';
    case Reopened = 'reopened';
}
