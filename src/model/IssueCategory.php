<?php

namespace App\Models;

enum IssueCategory: string
{
    case CRITICAL_SAFETY = 'CRITICAL_SAFETY';
    case MAJOR_BLOCKAGE  = 'MAJOR_BLOCKAGE';
    case MINOR_DEFECT    = 'MINOR_DEFECT';
    case AESTHETIC       = 'AESTHETIC';
    case OPERATIONAL     = 'OPERATIONAL'; // Retained for backward compatibility
}
