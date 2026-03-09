<?php

namespace App\Domain\Occurrence\Repositories;

use App\Domain\Occurrence\Entities\Occurrence;
use App\Domain\Occurrence\Enums\OccurrenceType;
use App\Domain\Occurrence\Enums\OccurrenceStatus;

interface OccurrenceRepositoryInterface
{
    /**
     * Persists an occurrence in the system.
     */
    public function save(Occurrence $occurrence): Occurrence;

    /**
     * Finds occurrences filtered by shift, type, and status.
     * Essential for the inheritance engine to identify open pending issues.
     */
    public function findByShiftTypeAndStatus(
        int $shiftId,
        OccurrenceType $type,
        OccurrenceStatus $status
    ): array;
}
