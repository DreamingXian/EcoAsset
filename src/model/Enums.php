<?php

namespace App\Models;

enum UserRole: string
{
    case TOURIST = 'TOURIST';
    case SITE_RANGER = 'SITE_RANGER';
    case MAINTENANCE_CREW = 'MAINTENANCE_CREW';
    case ADMINISTRATOR = 'ADMINISTRATOR';
}

enum SkillCategory: string
{
    case ELECTRICAL = 'ELECTRICAL';
    case STRUCTURAL = 'STRUCTURAL';
    case SANITATION = 'SANITATION';
}

enum AvailabilityState: string
{
    case AVAILABLE = 'AVAILABLE';
    case BUSY = 'BUSY';
}

enum IssueCategory: string
{
    case CRITICAL_SAFETY = 'CRITICAL_SAFETY';
    case OPERATIONAL = 'OPERATIONAL';
    case AESTHETIC = 'AESTHETIC';
}

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
