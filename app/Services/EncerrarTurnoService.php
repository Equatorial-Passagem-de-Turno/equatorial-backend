<?php

namespace App\Application\Services\Turno;

use App\Domain\Turno\Repositories\TurnoRepositoryInterface;
use App\Domain\Turno\Enums\TurnoStatus;
use Illuminate\Support\Facades\DB;
use DateTimeImmutable;
use Exception;

class EncerrarTurnoService
{
    protected $turnoRepository;

    public function __construct(TurnoRepositoryInterface $turnoRepository)
    {
        $this->turnoRepository = $turnoRepository;
    }

    public function execute(int $operadorId)
    {
        DB::beginTransaction();
        
        try {
            $turno = $this->turnoRepository->buscarTurnoAtivoPorOperador($operadorId);

            if (!$turno) {
                throw new Exception('Não existe nenhum turno ativo para este operador.');
            }

            $turno->status = TurnoStatus::FECHADO; 
            $turno->fim = new DateTimeImmutable();

            $this->turnoRepository->salvar($turno);

            DB::commit();

            return $turno;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}