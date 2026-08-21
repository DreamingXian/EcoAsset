<?php

namespace App\Models;

enum UserRole: string
{
    case TOURIST = 'TOURIST';
    case SITE_RANGER = 'SITE_RANGER';
    case MAINTENANCE_CREW = 'MAINTENANCE_CREW';
    case ADMINISTRATOR = 'ADMINISTRATOR';
}
