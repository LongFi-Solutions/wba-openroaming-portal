<?php

declare(strict_types=1);

namespace App\Enum;

enum AccessPointType: string
{
    case INDOOR = 'INDOOR';
    case OUTDOOR = 'OUTDOOR';
}
