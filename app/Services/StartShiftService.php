<?php

namespace App\Application\Services\Shift;

use App\Domain\Shift\Entities\Shift;
use App\Domain\Shift\Enums\ShiftStatus;
use App\Domain\Shift\Enums\VoltageLevel;
use App\Domain\Shift\Repositories\ShiftRepositoryInterface;
use App\Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;
use App\Domain\Occurrence\Enums\OccurrenceType;
use App\Domain\Occurrence\Enums\OccurrenceStatus;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Exception;

class StartShiftService
{
    public function __construct(
        protected ShiftRepositoryInterface $shiftRepository,
        protected OccurrenceRepositoryInterface $occurrenceRepository
    ) {}

    /**
     * Starts a new shift for the operator, performing vertical inheritance of pending occurrences.
     * * @param int $userId
     * @return Shift
     * @throws Exception
     */
    public function execute(int $userId): Shift
    {
        DB::beginTransaction();

        try {
            // 1. Validate if the operator already has an active shift
            $activeShift = $this->shiftRepository->findActiveShiftByUserId($userId);
            if ($activeShift) {
                throw new Exception('There is already a shift in progress for this operator.');
            }

            // 2. Identify Operator Profile (Voltage Level)
            $user = User::findOrFail($userId);
            $userVoltageLevel = VoltageLevel::from($user->voltage_level);

            // 3. Search for the Last Finished Shift for Vertical Inheritance
            $previousShift = $this->shiftRepository->findLastFinishedByVoltage($userVoltageLevel);

            // 4. Instantiate and Save the New Shift
            $newShift = new Shift(
                id: null,
                userId: $userId,
                start: new DateTimeImmutable(),
                end: null,
                status: ShiftStatus::IN_PROGRESS,
                voltageLevel: $userVoltageLevel,
                previousShiftId: $previousShift?->id
            );

            $savedShift = $this->shiftRepository->save($newShift);

            // 5. Inheritance Engine: Transfer open pending occurrences
            if ($previousShift) {
                $pendingOccurrences = $this->occurrenceRepository->findByShiftTypeAndStatus(
                    $previousShift->id,
                    OccurrenceType::PENDING,
                    OccurrenceStatus::OPEN
                );

                foreach ($pendingOccurrences as $occurrence) {
                    $occurrence->shiftId = $savedShift->id;
                    $this->occurrenceRepository->save($occurrence);
                }
            }

            DB::commit();
            return $savedShift;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
