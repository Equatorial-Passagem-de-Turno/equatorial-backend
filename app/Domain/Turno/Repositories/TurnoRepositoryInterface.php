<?php

namespace App\Domain\Turno\Repositories;

use App\Domain\Turno\Entities\Turno;

interface TurnoRepositoryInterface
{
    public function salvar(Turno $turno): Turno;

    public function buscarPorId(int $id): ?Turno;

    public function buscarTurnoAtivoPorOperador(int $operadorId): ?Turno;
}
