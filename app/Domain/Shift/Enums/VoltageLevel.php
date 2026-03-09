<?php

namespace App\Domain\Shift\Enums;

enum VoltageLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
