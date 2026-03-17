<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shift;
use App\Models\User;
use App\Models\OperationDesk;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $desk = OperationDesk::first();

        if (!$user || !$desk) {
            $this->command->warn('⚠️ Usuários ou Mesas não encontrados. Rode os seeders anteriores primeiro.');
            return;
        }

        Shift::create([
            'user_id' => $user->id,
            'operation_desk_id' => $desk->id,
            'role' => 'MT',
            'start' => now()->subHours(8),
            'end' => now(),
            'status' => 'finished',
            'briefing' => 'Turno encerrado via Seeder com 3 ocorrências pendentes repassadas para a próxima equipe.',
        ]);
    }
}
