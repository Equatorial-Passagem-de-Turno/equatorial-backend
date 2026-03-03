<?php

namespace App\Domain\Turno\Entities;

use App\Domain\Turno\Enums\TurnoStatus;
use DomainException;

class Turno
{
    public function __construct(
        public ?int $id,
        public int $operadorId,
        public \DateTimeImmutable $inicio,
        public ?\DateTimeImmutable $fim,
        public TurnoStatus $status,
        public ?string $briefingFinal = null
    ) {}

    public function iniciar(): void
    {
        if ($this->status !== TurnoStatus::ABERTO) {
            throw new DomainException('Turno não pode ser iniciado.');
        }

        $this->status = TurnoStatus::EM_ANDAMENTO;
    }

    public function finalizar(string $briefing): void
    {
        if ($this->status !== TurnoStatus::EM_ANDAMENTO) {
            throw new DomainException('Somente turnos em andamento podem ser finalizados.');
        }

        $this->status = TurnoStatus::FINALIZADO;
        $this->fim = new \DateTimeImmutable();
        $this->briefingFinal = $briefing;
    }
}
