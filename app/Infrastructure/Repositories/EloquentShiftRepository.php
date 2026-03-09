<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Shift\Entities\Shift;
use App\Domain\Shift\Enums\ShiftStatus;
use App\Domain\Shift\Enums\VoltageLevel;
use App\Domain\Shift\Repositories\ShiftRepositoryInterface;
use App\Infrastructure\Persistence\EloquentShiftModel;
use DateTimeImmutable;

class EloquentShiftRepository implements ShiftRepositoryInterface
{
    /**
     * Persists or updates the Shift entity in the database.
     */
    public function save(Shift $shift): Shift
    {
        $model = $shift->id
            ? EloquentShiftModel::findOrFail($shift->id)
            : new EloquentShiftModel();

        $model->user_id = $shift->userId;
        $model->start = $shift->start->format('Y-m-d H:i:s');
        $model->end = $shift->end ? $shift->end->format('Y-m-d H:i:s') : null;
        $model->status = $shift->status->value;
        $model->voltage_level = $shift->voltageLevel->value;
        $model->previous_shift_id = $shift->previousShiftId;
        $model->observations = $shift->finalBriefing;

        $model->save();

        return $this->mapToEntity($model);
    }

    /**
     * Finds a shift by ID.
     */
    public function findById(int $id): ?Shift
    {
        $model = EloquentShiftModel::find($id);
        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * Finds the currently active shift for a user.
     */
    public function findActiveShiftByUserId(int $userId): ?Shift
    {
        $model = EloquentShiftModel::where('user_id', $userId)
            ->where('status', ShiftStatus::IN_PROGRESS->value)
            ->first();

        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * Finds the last shift that was finished by a specific user.
     */
    public function findLastFinishedByUserId(int $userId): ?Shift
    {
        $model = EloquentShiftModel::where('user_id', $userId)
            ->where('status', ShiftStatus::FINISHED->value)
            ->orderBy('end', 'desc')
            ->first();

        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * Finds the last finished shift in the system for a specific voltage level.
     * Crucial for vertical inheritance.
     */
    public function findLastFinishedByVoltage(VoltageLevel $voltage): ?Shift
    {
        $model = EloquentShiftModel::where('status', ShiftStatus::FINISHED->value)
            ->where('voltage_level', $voltage->value)
            ->orderBy('end', 'desc')
            ->first();

        return $model ? $this->mapToEntity($model) : null;
    }

    /**
     * Maps the Eloquent Model to the Domain Entity.
     */
    private function mapToEntity(EloquentShiftModel $model): Shift
    {
        return new Shift(
            id: $model->id,
            userId: $model->user_id,
            start: new DateTimeImmutable($model->start),
            end: $model->end ? new DateTimeImmutable($model->end) : null,
            status: ShiftStatus::from($model->status),
            voltageLevel: VoltageLevel::from($model->voltage_level),
            previousShiftId: $model->previous_shift_id,
            finalBriefing: $model->observations
        );
    }
}
