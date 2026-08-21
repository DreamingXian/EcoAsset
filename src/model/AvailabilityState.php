<?php

namespace App\Models;

enum AvailabilityState: string
{
    case AVAILABLE = 'AVAILABLE';
    case BUSY = 'BUSY';
}
