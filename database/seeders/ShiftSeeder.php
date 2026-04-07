<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shift;
use App\Models\User;
use App\Models\OperationDesk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $testUser = User::where('email', 'teste@teste.com')->first();
        $previousOperator = User::where('email', 'carlos.oliveira@eqcontinuum.local')->first();
        $desk = OperationDesk::where('code', 'MESA-01')->first() ?? OperationDesk::first();

        if (!$testUser || !$previousOperator || !$desk) {
            $this->command->warn('⚠️ Usuários ou Mesas não encontrados. Rode os seeders anteriores primeiro.');
            return;
        }

        DB::table('occurrences')->delete();
        DB::table('shifts')->delete();

        $now = Carbon::now();

        $shiftPrevious = Shift::create([
            'user_id' => $previousOperator->id,
            'operation_desk_id' => $desk->id,
            'role' => 'Baixa Tensão (BT)',
            'start' => $now->copy()->subHours(16),
            'end' => $now->copy()->subHours(8),
            'status' => 'finished',
            'briefing' => 'Turno com pendências importantes repassadas para o próximo operador da mesa.',
            'handover_acknowledged' => false,
        ]);

        Shift::create([
            'user_id' => $testUser->id,
            'operation_desk_id' => $desk->id,
            'role' => 'Baixa Tensão (BT)',
            'start' => $now->copy()->subHours(2),
            'end' => null,
            'status' => 'in_progress',
            'briefing' => 'Turno em andamento para demonstração da dashboard e do controle de turnos.',
            'previous_shift_id' => $shiftPrevious->id,
            'handover_acknowledged' => false,
        ]);

        Shift::create([
            'user_id' => User::where('email', 'fernanda.souza@eqcontinuum.local')->value('id') ?? $previousOperator->id,
            'operation_desk_id' => $desk->id,
            'role' => 'Média Tensão (MT)',
            'start' => $now->copy()->subDay()->setTime(8, 0),
            'end' => $now->copy()->subDay()->setTime(16, 5),
            'status' => 'finished',
            'briefing' => 'Turno histórico fechado para alimentar a visão de histórico.',
            'handover_acknowledged' => true,
        ]);
    }
}
