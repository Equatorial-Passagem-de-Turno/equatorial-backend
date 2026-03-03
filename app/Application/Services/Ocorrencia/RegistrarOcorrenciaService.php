<?php

namespace App\Application\Services\Ocorrencia;

use App\Domain\Ocorrencia\Entities\Ocorrencia;
use App\Domain\Ocorrencia\Enums\OcorrenciaTipo;
use App\Domain\Ocorrencia\Enums\OcorrenciaStatus;
use App\Domain\Ocorrencia\Repositories\OcorrenciaRepositoryInterface;
use App\Domain\Turno\Repositories\TurnoRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class RegistrarOcorrenciaService
{
    public function __construct(
        protected OcorrenciaRepositoryInterface $ocorrenciaRepository,
        protected TurnoRepositoryInterface $turnoRepository
    ) {}

    public function execute(int $operadorId, array $dados): Ocorrencia
    {
        DB::beginTransaction();
        
        try {
            $turnoAtivo = $this->turnoRepository->buscarTurnoAtivoPorOperador($operadorId);

            if (!$turnoAtivo) {
                throw new Exception('Você precisa ter um turno em andamento para registrar uma ocorrência.');
            }

            $ocorrencia = new Ocorrencia(
                id: null,
                turnoId: $turnoAtivo->id,
                titulo: $dados['titulo'],
                descricao: $dados['descricao'],
                tipo: OcorrenciaTipo::from($dados['tipo']),
                status: OcorrenciaStatus::ABERTA
            );

            $ocorrenciaSalva = $this->ocorrenciaRepository->salvar($ocorrencia);

            DB::commit();

            return $ocorrenciaSalva;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}