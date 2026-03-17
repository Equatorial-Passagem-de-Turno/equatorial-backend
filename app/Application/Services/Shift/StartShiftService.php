<?php

namespace App\Application\Services\Shift;

use App\Models\User;
use App\Models\OperationDesk;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Exception;

class StartShiftService
{
    public function execute(int $userId, $deskId, string $role): Shift
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($userId);
            $desk = OperationDesk::findOrFail($deskId);

            if ($user->shifts()->where('status', 'in_progress')->exists()) {
                throw new Exception('Já existe um turno em andamento para este operador.');
            }

            $previousShift = Shift::where('operation_desk_id', $desk->id)
                ->where('status', 'finished')
                ->latest('end')
                ->first();

            $newShift = $user->shifts()->create([
                'operation_desk_id' => $desk->id,
                'role'              => $role,
                'start'             => now(),
                'status'            => 'in_progress',
                'previous_shift_id' => $previousShift?->id,
            ]);

            DB::commit();
            return $newShift;

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Erro ao iniciar o turno: " . $e->getMessage());
        }
    }
}
