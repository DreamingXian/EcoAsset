<?php

namespace App\Models;

enum IssueCategory: string
{
    case CRITICAL_SAFETY = 'CRITICAL_SAFETY';
    case OPERATIONAL = 'OPERATIONAL';
    case AESTHETIC = 'AESTHETIC';
}
