<?php

namespace App\Models;

enum UserRole: string
{
    case TOURIST = 'TOURIST';
    case MAINTENANCE_CREW = 'MAINTENANCE_CREW';
    case ADMINISTRATOR = 'ADMINISTRATOR';
}
