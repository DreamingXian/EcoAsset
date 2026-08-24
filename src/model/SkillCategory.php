<?php

namespace App\Models;

enum SkillCategory: string
{
    case ELECTRICAL       = 'ELECTRICAL';
    case STRUCTURAL       = 'STRUCTURAL';
    case SANITATION       = 'SANITATION';
    case GENERAL_FACILITY = 'GENERAL_FACILITY';
    case OTHER            = 'OTHER';
}
