<?php

namespace App\Application\Services\Occurrence;

use App\Domain\Shift\Repositories\ShiftRepositoryInterface;
use App\Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;
use App\Domain\Occurrence\Entities\Occurrence;
use App\Domain\Occurrence\Enums\OccurrenceType;
use App\Domain\Occurrence\Enums\OccurrenceStatus;
use Illuminate\Support\Facades\DB;
use Exception;

class RegisterOccurrenceService
{
    public function __construct(
        protected OccurrenceRepositoryInterface $occurrenceRepository,
        protected ShiftRepositoryInterface $shiftRepository
    ) {}

    /**
     * Registers a new occurrence linked to the user's current active shift.
     * * @param int $userId
     * @param array $data
     * @return Occurrence
     * @throws Exception
     */
    public function execute(int $userId, array $data): Occurrence
    {
        DB::beginTransaction();

        try {
            // 1. Validate if the user has an active shift
            $activeShift = $this->shiftRepository->findActiveShiftByUserId($userId);

            if (!$activeShift) {
                throw new Exception('You must have a shift in progress to register an occurrence.');
            }

            // 2. Instantiate the Domain Entity with translated properties
            $occurrence = new Occurrence(
                id: null,
                shiftId: $activeShift->id,
                title: $data['title'],
                description: $data['description'],
                type: OccurrenceType::from($data['type']),
                status: OccurrenceStatus::OPEN
            );

            // 3. Persist using the translated repository
            $savedOccurrence = $this->occurrenceRepository->save($occurrence);

            DB::commit();

            return $savedOccurrence;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
