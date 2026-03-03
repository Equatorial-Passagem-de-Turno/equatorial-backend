<?php

namespace App\Domain\Ocorrencia\Enums;

enum OcorrenciaStatus: string
{
    case ABERTA = 'ABERTA';
    case EM_ANDAMENTO = 'EM_ANDAMENTO';
    case CONCLUIDA = 'CONCLUIDA';
}