<?php

namespace App\Domain\Shift\Enums;

enum ShiftStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case FINISHED = 'finished';
}
