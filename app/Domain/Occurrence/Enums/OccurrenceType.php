<?php

namespace App\Domain\Occurrence\Enums;

enum OccurrenceType: string
{
    case ROUTINE = 'routine';
    case PENDING = 'pending';
    case EMERGENCY = 'emergency';
}
