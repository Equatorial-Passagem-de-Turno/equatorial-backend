<?php

namespace App\Domain\Ocorrencia\Entities;

use App\Domain\Ocorrencia\Enums\OcorrenciaTipo;
use App\Domain\Ocorrencia\Enums\OcorrenciaStatus;
use DateTimeImmutable;

class Ocorrencia
{
    public function __construct(
        public ?int $id = null,
        public int $turnoId,
        public string $titulo,
        public string $descricao,
        public OcorrenciaTipo $tipo,
        public OcorrenciaStatus $status,
        public ?int $supervisorId = null,
        public ?DateTimeImmutable $criadoEm = null,
    ) {}
}