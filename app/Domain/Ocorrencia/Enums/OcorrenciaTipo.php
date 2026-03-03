<?php

namespace App\Domain\Ocorrencia\Enums;

enum OcorrenciaTipo: string
{
    case ROTINA = 'ROTINA';
    case PENDENCIA = 'PENDENCIA';
    case EMERGENCIA = 'EMERGENCIA';
}