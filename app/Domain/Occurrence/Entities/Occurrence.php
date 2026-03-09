<?php

namespace App\Domain\Occurrence\Entities;

use App\Domain\Occurrence\Enums\OccurrenceType;
use App\Domain\Occurrence\Enums\OccurrenceStatus;
use DateTimeImmutable;

class Occurrence
{
    public function __construct(
        public ?int $id = null,
        public int $shiftId,
        public string $title,
        public string $description,
        public OccurrenceType $type,
        public OccurrenceStatus $status,
        public ?int $supervisorId = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}
}
