<?php

namespace App\Application\Services\Shift;

use App\Domain\Shift\Repositories\ShiftRepositoryInterface;
use App\Domain\Shift\Enums\ShiftStatus;
use App\Domain\Shift\Entities\Shift;
use Illuminate\Support\Facades\DB;
use DateTimeImmutable;
use Exception;

class FinishShiftService
{
    public function __construct(
        protected ShiftRepositoryInterface $shiftRepository
    ) {}

    /**
     * Finishes the current shift for the user.
     * * @param int $userId
     * @param string|null $briefing
     * @return Shift
     * @throws Exception
     */
    public function execute(int $userId, ?string $briefing = null): Shift
    {
        DB::beginTransaction();

        try {
            // 1. Busca o turno ativo usando o novo padrão de repositório
            $shift = $this->shiftRepository->findActiveShiftByUserId($userId);

            if (!$shift) {
                throw new Exception('No active shift found for this operator.');
            }

            // 2. Utiliza o método finish da entidade Shift para garantir a lógica de domínio
            // Isso atualizará o status para FINISHED, definirá o fim e o briefing
            $shift->finish($briefing);

            // 3. Salva a entidade atualizada
            $savedShift = $this->shiftRepository->save($shift);

            DB::commit();

            return $savedShift;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
