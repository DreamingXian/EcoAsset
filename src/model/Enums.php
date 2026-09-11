<?php

namespace App\Models;

enum UserRole: string
{
    case TOURIST = 'TOURIST';
    case MAINTENANCE_CREW = 'MAINTENANCE_CREW';
    case ADMINISTRATOR = 'ADMINISTRATOR';
}

enum SkillCategory: string
{
    case ELECTRICAL = 'ELECTRICAL';
    case STRUCTURAL = 'STRUCTURAL';
    case SANITATION = 'SANITATION';
    case GENERAL_FACILITY = 'GENERAL_FACILITY';
    case OTHER = 'OTHER';
}

enum AvailabilityState: string
{
    case AVAILABLE = 'AVAILABLE';
    case BUSY = 'BUSY';
}

enum IssueCategory: string
{
    case CRITICAL_SAFETY = 'CRITICAL_SAFETY';
    case MAJOR_BLOCKAGE = 'MAJOR_BLOCKAGE';
    case MINOR_DEFECT = 'MINOR_DEFECT';
    case AESTHETIC = 'AESTHETIC';
    case OPERATIONAL = 'OPERATIONAL';
}

enum ReportStatus: string
{
    case OPEN_FOR_CLAIM = 'OPEN_FOR_CLAIM';
    case PENDING_ACCEPTANCE = 'PENDING_ACCEPTANCE';
    case IN_PROGRESS = 'IN_PROGRESS';
    case PRECOMPLETE = 'PRECOMPLETE';
    case DROPPED = 'DROPPED';
    case PRE_INVALID = 'PRE_INVALID';
    case COMPLETED = 'COMPLETED';
    case INVALID_RESOLVED = 'INVALID_RESOLVED';
}
