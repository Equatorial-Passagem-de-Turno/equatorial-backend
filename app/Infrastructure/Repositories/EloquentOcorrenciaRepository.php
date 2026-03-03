<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Ocorrencia\Entities\Ocorrencia;
use App\Domain\Ocorrencia\Enums\OcorrenciaTipo;
use App\Domain\Ocorrencia\Enums\OcorrenciaStatus;
use App\Domain\Ocorrencia\Repositories\OcorrenciaRepositoryInterface;
use App\Infrastructure\Persistence\EloquentOcorrenciaModel;
use DateTimeImmutable;

class EloquentOcorrenciaRepository implements OcorrenciaRepositoryInterface
{
    public function salvar(Ocorrencia $ocorrencia): Ocorrencia
    {
        $model = $ocorrencia->id
            ? EloquentOcorrenciaModel::findOrFail($ocorrencia->id)
            : new EloquentOcorrenciaModel();

        $model->turno_id = $ocorrencia->turnoId;
        $model->titulo = $ocorrencia->titulo;
        $model->descricao = $ocorrencia->descricao;
        $model->tipo = $ocorrencia->tipo->value;
        $model->status = $ocorrencia->status->value;
        $model->supervisor_id = $ocorrencia->supervisorId;

        $model->save();

        return new Ocorrencia(
            id: $model->id,
            turnoId: $model->turno_id,
            titulo: $model->titulo,
            descricao: $model->descricao,
            tipo: OcorrenciaTipo::from($model->tipo),
            status: OcorrenciaStatus::from($model->status),
            supervisorId: $model->supervisor_id,
            criadoEm: new DateTimeImmutable($model->created_at)
        );
    }
}