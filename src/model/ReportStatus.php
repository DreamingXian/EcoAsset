<?php

namespace App\Models;

enum ReportStatus: string
{
    case OPEN_FOR_CLAIM = 'OPEN_FOR_CLAIM';
    case PENDING_ACCEPTANCE = 'PENDING_ACCEPTANCE';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case INVALID_RESOLVED = 'INVALID_RESOLVED';
}
