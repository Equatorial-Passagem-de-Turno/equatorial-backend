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

            // Verifica se o usuário já tem turno ativo
            if ($user->shifts()->where('status', 'in_progress')->exists()) {
                throw new Exception('Já existe um turno em andamento para este operador.');
            }

            // Busca o último turno da MESMA MESA para fazer a Herança de pendências
            $previousShift = Shift::where('operation_desk_id', $desk->id)
                ->where('status', 'finished')
                ->latest('end')
                ->first();

            // Criamos o turno a partir do relacionamento com o Usuário
            $newShift = $user->shifts()->make([
                'role'              => $role,
                'start'             => now(),
                'status'            => 'in_progress',
                'previous_shift_id' => $previousShift?->id,
            ]);

            // Associa a mesa e salva
            $newShift->desk()->associate($desk);
            $newShift->save();

            // transfere as ocorrências abertas da mesa para este novo turno
            if ($previousShift) {
                $previousShift->occurrences()
                    ->where('type', 'pending')
                    ->where('status', 'open')
                    ->update(['shift_id' => $newShift->id]);
            }

            DB::commit();
            
            return $newShift;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}