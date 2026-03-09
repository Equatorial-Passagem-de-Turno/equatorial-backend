<?php

namespace App\Domain\Shift\Repositories;

use App\Domain\Shift\Entities\Shift;
use App\Domain\Shift\Enums\VoltageLevel;

interface ShiftRepositoryInterface
{
    /**
     * Persists or updates a shift in the system.
     */
    public function save(Shift $shift): Shift;

    /**
     * Finds a shift by its unique identifier.
     */
    public function findById(int $id): ?Shift;

    /**
     * Checks if the operator already has a shift with IN_PROGRESS status.
     */
    public function findActiveShiftByUserId(int $userId): ?Shift;

    /**
     * Gets the last shift that was moved to FINISHED status by a specific operator.
     */
    public function findLastFinishedByUserId(int $userId): ?Shift;

    /**
     * Finds the last finished shift in the system filtering by voltage level.
     * Essential for Vertical Inheritance logic between different operators of the same profile.
     */
    public function findLastFinishedByVoltage(VoltageLevel $voltage): ?Shift;
}
