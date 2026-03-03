<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Turno\Entities\Turno;
use App\Domain\Turno\Enums\TurnoStatus;
use App\Domain\Turno\Repositories\TurnoRepositoryInterface;
use App\Infrastructure\Persistence\EloquentTurnoModel;
use DateTimeImmutable;

class EloquentTurnoRepository implements TurnoRepositoryInterface
{
    public function salvar(Turno $turno): Turno
    {
        $model = $turno->id
            ? EloquentTurnoModel::findOrFail($turno->id)
            : new EloquentTurnoModel();

        $model->usuario_id = $turno->operadorId;
        $model->inicio = $turno->inicio;
        $model->fim = $turno->fim;
        $model->status = $turno->status->value;
        $model->observacoes = $turno->briefingFinal;

        $model->save();

        return new Turno(
            id: $model->id,
            operadorId: $model->usuario_id, 
            inicio: new DateTimeImmutable($model->inicio),
            fim: $model->fim ? new DateTimeImmutable($model->fim) : null,
            status: TurnoStatus::from($model->status),
            briefingFinal: $model->observacoes
        );
    }

    public function buscarPorId(int $id): ?Turno
    {
        $model = EloquentTurnoModel::find($id);

        if (!$model) {
            return null;
        }

        return new Turno(
            id: $model->id,
            operadorId: $model->usuario_id, 
            inicio: new DateTimeImmutable($model->inicio),
            fim: $model->fim ? new DateTimeImmutable($model->fim) : null,
            status: TurnoStatus::from($model->status),
            briefingFinal: $model->briefing_final
        );
    }

    public function buscarTurnoAtivoPorOperador(int $operadorId): ?Turno
    {
        $model = EloquentTurnoModel::where('usuario_id', $operadorId) 
            ->where('status', TurnoStatus::EM_ANDAMENTO->value)
            ->first();

        if (!$model) {
            return null;
        }

        return new Turno(
            id: $model->id,
            operadorId: $model->usuario_id, 
            inicio: new DateTimeImmutable($model->inicio),
            fim: $model->fim ? new DateTimeImmutable($model->fim) : null,
            status: TurnoStatus::from($model->status),
            briefingFinal: $model->briefing_final
        );
    }
}