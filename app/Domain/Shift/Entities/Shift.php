<?php

namespace App\Domain\Shift\Entities;

use App\Domain\Shift\Enums\ShiftStatus;
use App\Domain\Shift\Enums\VoltageLevel;
use DomainException;

class Shift
{
    /**
     * @param int|null $id
     * @param int $userId
     * @param \DateTimeImmutable $start
     * @param \DateTimeImmutable|null $end
     * @param ShiftStatus $status
     * @param VoltageLevel $voltageLevel
     * @param int|null $previousShiftId
     * @param string|null $finalBriefing
     */
    public function __construct(
        public ?int $id,
        public int $userId,
        public \DateTimeImmutable $start,
        public ?\DateTimeImmutable $end,
        public ShiftStatus $status,
        public VoltageLevel $voltageLevel,
        public ?int $previousShiftId = null,
        public ?string $finalBriefing = null
    ) {}

    /**
     * Starts the shift by changing its state to in progress.
     * @throws DomainException
     */
    public function start(): void
    {
        if ($this->status !== ShiftStatus::OPEN) {
            throw new DomainException('Shift cannot be started because it is not in Open state.');
        }

        $this->status = ShiftStatus::IN_PROGRESS;
    }

    /**
     * Finishes the shift, sets the end date, and records the briefing.
     * @param string $briefing
     * @throws DomainException
     */
    public function finish(string $briefing): void
    {
        if ($this->status !== ShiftStatus::IN_PROGRESS) {
            throw new DomainException('Only shifts in progress can be finished.');
        }

        $this->status = ShiftStatus::FINISHED;
        $this->end = new \DateTimeImmutable();
        $this->finalBriefing = $briefing;
    }
}
