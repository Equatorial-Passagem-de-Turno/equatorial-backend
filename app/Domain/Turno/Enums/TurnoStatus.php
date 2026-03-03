<?php

namespace App\Domain\Turno\Enums;

enum TurnoStatus: string
{
    case EM_ANDAMENTO = 'aberto';
    case FINALIZADO = 'fechado';
}