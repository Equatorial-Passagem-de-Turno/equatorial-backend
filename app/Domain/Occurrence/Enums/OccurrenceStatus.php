<?php

namespace App\Domain\Occurrence\Enums;

/**
 * Define os estados possíveis de uma ocorrência.
 */
enum OccurrenceStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
