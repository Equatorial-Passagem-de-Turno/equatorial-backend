<?php

namespace App\Application\Services\Turno;

use App\Domain\Turno\Entities\Turno;
use App\Domain\Turno\Enums\TurnoStatus;
use App\Domain\Turno\Repositories\TurnoRepositoryInterface;
use DomainException;
use DateTimeImmutable;

class IniciarTurnoService
{
    public function __construct(
        private TurnoRepositoryInterface $turnoRepository
    ) {}

    public function executar(int $operadorId): Turno
    {
        // 1️⃣ Verificar se já existe turno ativo
        $turnoAtivo = $this->turnoRepository
            ->buscarTurnoAtivoPorOperador($operadorId);

        if ($turnoAtivo) {
            throw new DomainException(
                'Já existe um turno em andamento para este operador.'
            );
        }

        // 2️⃣ Criar novo turno
        $novoTurno = new Turno(
            id: null,
            operadorId: $operadorId,
            inicio: new DateTimeImmutable(),
            fim: null,
            status: TurnoStatus::EM_ANDAMENTO
        );

        // 3️⃣ Salvar no banco
        return $this->turnoRepository->salvar($novoTurno);
    }
}
