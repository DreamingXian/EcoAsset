<?php

namespace App\Models;

enum ReportStatus: string
{
    case PENDING_VALIDATION = 'PENDING_VALIDATION';
    case VERIFIED = 'VERIFIED';
    case PENDING_ACCEPTANCE = 'PENDING_ACCEPTANCE';
    case IN_PROGRESS = 'IN_PROGRESS';
    case OPEN_FOR_CLAIM = 'OPEN_FOR_CLAIM';
    case COMPLETED = 'COMPLETED';
    case INVALID_RESOLVED = 'INVALID_RESOLVED';
}
