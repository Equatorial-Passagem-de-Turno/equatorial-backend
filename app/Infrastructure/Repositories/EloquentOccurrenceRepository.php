<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Occurrence\Entities\Occurrence;
use App\Domain\Occurrence\Enums\OccurrenceType;
use App\Domain\Occurrence\Enums\OccurrenceStatus;
use App\Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;
use App\Infrastructure\Persistence\EloquentOccurrenceModel;
use DateTimeImmutable;

class EloquentOccurrenceRepository implements OccurrenceRepositoryInterface
{
    /**
     * Saves the occurrence entity to the database.
     */
    public function save(Occurrence $occurrence): Occurrence
    {
        $model = $occurrence->id
            ? EloquentOccurrenceModel::findOrFail($occurrence->id)
            : new EloquentOccurrenceModel();

        $model->shift_id = $occurrence->shiftId;
        $model->title = $occurrence->title;
        $model->description = $occurrence->description;
        $model->type = $occurrence->type->value;
        $model->status = $occurrence->status->value;
        $model->supervisor_id = $occurrence->supervisorId;

        $model->save();

        return $this->mapToEntity($model);
    }

    /**
     * Finds occurrences filtered by shift, type, and status.
     * Essential for the inheritance engine to identify open pending issues.
     */
    public function findByShiftTypeAndStatus(
        int $shiftId,
        OccurrenceType $type,
        OccurrenceStatus $status
    ): array {
        $models = EloquentOccurrenceModel::where('shift_id', $shiftId)
            ->where('type', $type->value)
            ->where('status', $status->value)
            ->get();

        return $models->map(fn($model) => $this->mapToEntity($model))->toArray();
    }

    /**
     * Maps the Eloquent Model to the Domain Entity.
     */
    private function mapToEntity(EloquentOccurrenceModel $model): Occurrence
    {
        return new Occurrence(
            id: $model->id,
            shiftId: $model->shift_id,
            title: $model->title,
            description: $model->description,
            type: OccurrenceType::from($model->type),
            status: OccurrenceStatus::from($model->status),
            supervisorId: $model->supervisor_id,
            createdAt: new DateTimeImmutable($model->created_at)
        );
    }
}
