<?php

namespace App\Domain\Ocorrencia\Repositories;

use App\Domain\Ocorrencia\Entities\Ocorrencia;

interface OcorrenciaRepositoryInterface
{
    public function salvar(Ocorrencia $ocorrencia): Ocorrencia;
}