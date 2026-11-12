<?php

namespace App\Enums;

enum UserRole: string
{
    case Employee = 'employee';
    case Handler = 'handler';
    case Admin = 'admin';
}
