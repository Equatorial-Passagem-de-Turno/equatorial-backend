<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Occurrence;
use App\Models\Shift;

class OccurrenceSeeder extends Seeder
{
    public function run(): void
    {
        $shift = Shift::latest()->first();

        if (!$shift) {
            $this->command->warn('Nenhum turno encontrado! Abra um turno no painel primeiro antes de rodar este seeder.');
            return;
        }

        $occurrences = [
            [
                'title' => 'Cabo rompido na Avenida Principal',
                'category' => 'Emergência',
                'priority' => 'crítica',
                'status' => 'Aberta',
                'description' => 'Fio de alta tensão partido no chão. A equipe isolou a área, mas aguarda caminhão cesto.',
                'location' => 'Av. Principal, 1200 - Centro',
            ],
            [
                'title' => 'Oscilação de tensão no alimentador 04',
                'category' => 'Monitoramento',
                'priority' => 'alta',
                'status' => 'Aberta',
                'description' => 'Clientes relatando picos de energia. Necessário acompanhamento contínuo.',
                'location' => 'Subestação Sul',
            ],
            [
                'title' => 'Manutenção preventiva no relé',
                'category' => 'Manutenção',
                'priority' => 'baixa',
                'status' => 'Aberta',
                'description' => 'Serviço agendado para o final do dia. Repassar para o próximo turno confirmar a execução.',
                'location' => 'Subestação Norte',
            ]
        ];

        foreach ($occurrences as $occ) {
            Occurrence::create(array_merge($occ, [
                'id' => 'OC-' . date('Ym') . '-' . rand(1000, 9999),
                'user_id' => $shift->user_id,
                'shift_id' => $shift->id,
            ]));
        }
    }
}
