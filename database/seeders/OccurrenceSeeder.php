<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Occurrence;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OccurrenceSeeder extends Seeder
{
    public function run(): void
    {
        $currentShift = Shift::where('status', 'in_progress')->latest('start')->first();
        $previousShift = null;

        if ($currentShift && $currentShift->previous_shift_id) {
            $previousShift = Shift::find($currentShift->previous_shift_id);
        }

        if (!$currentShift || !$previousShift) {
            $this->command->warn('Nenhum turno ativo/anterior encontrado. Rode o ShiftSeeder primeiro.');
            return;
        }

        DB::table('occurrences')->delete();

        $inheritedPending = [
            [
                'id' => 'OC-' . now()->format('Ym') . '-1101',
                'title' => 'Risco de cabo partido em via pública',
                'category' => 'Herdada de Turno',
                'priority' => 'critical',
                'status' => 'open',
                'description' => 'Equipe isolou parcialmente a área e aguarda apoio para finalização segura.',
                'location' => [
                    'address' => 'Av. Fernandes Lima, 1200',
                    'neighborhood' => 'Farol',
                    'city' => 'Maceio',
                    'alimentador' => 'AL-04',
                    'subestacao' => 'SE-MCZ-1',
                ],
                'created_at' => Carbon::parse($currentShift->start)->subHours(5),
            ],
            [
                'id' => 'OC-' . now()->format('Ym') . '-1102',
                'title' => 'Oscilacao intermitente no alimentador AL-08',
                'category' => 'Herdada de Turno',
                'priority' => 'high',
                'status' => 'in_progress',
                'description' => 'Ocorrencia transferida para acompanhamento no turno atual.',
                'location' => [
                    'address' => 'Rua da Praia',
                    'neighborhood' => 'Pajucara',
                    'city' => 'Maceio',
                    'alimentador' => 'AL-08',
                    'subestacao' => 'SE-MCZ-2',
                ],
                'created_at' => Carbon::parse($currentShift->start)->subHours(4),
            ],
        ];

        $previousResolved = [
            [
                'id' => 'OC-' . now()->format('Ym') . '-1199',
                'title' => 'Inspecao concluida em religador R-12',
                'category' => 'Manutencao',
                'priority' => 'low',
                'status' => 'resolved',
                'description' => 'Servico finalizado no turno anterior para compor historico.',
                'location' => [
                    'address' => 'Subestacao Norte',
                    'city' => 'Maceio',
                ],
                'created_at' => Carbon::parse($currentShift->start)->subHours(6),
            ]
        ];

        $currentShiftItems = [
            [
                'id' => 'OC-' . now()->format('Ym') . '-2101',
                'title' => 'Nova ocorrencia criada no turno atual',
                'category' => 'Atendimento ao Cliente',
                'priority' => 'medium',
                'status' => 'open',
                'description' => 'Item criado durante o turno ativo para validar exibicao na dashboard.',
                'location' => [
                    'address' => 'Rua do Comercio',
                    'neighborhood' => 'Centro',
                    'city' => 'Maceio',
                ],
                'created_at' => Carbon::parse($currentShift->start)->addMinutes(25),
            ],
            [
                'id' => 'OC-' . now()->format('Ym') . '-2102',
                'title' => 'Pendencia monitorada pelo operador atual',
                'category' => 'Monitoramento',
                'priority' => 'high',
                'status' => 'open',
                'description' => 'Pendencia aberta para alimentar cards e filtros de prioridade.',
                'location' => [
                    'address' => 'Av. Durval de Goes Monteiro',
                    'city' => 'Maceio',
                ],
                'created_at' => Carbon::parse($currentShift->start)->addMinutes(50),
            ],
        ];

        foreach ($inheritedPending as $occ) {
            Occurrence::create([
                'id' => $occ['id'],
                'user_id' => $previousShift->user_id,
                'shift_id' => $previousShift->id,
                'title' => $occ['title'],
                'category' => $occ['category'],
                'priority' => $occ['priority'],
                'status' => $occ['status'],
                'description' => $occ['description'],
                'location' => $occ['location'],
                'comments' => [[
                    'id' => 'sys-' . uniqid(),
                    'author' => 'Sistema',
                    'text' => 'Ocorrencia aberta no turno anterior para heranca de demonstracao.',
                    'type' => 'Sistema',
                    'createdAt' => now()->toISOString(),
                ]],
                'created_at' => $occ['created_at'],
                'updated_at' => $occ['created_at'],
            ]);
        }

        foreach ($previousResolved as $occ) {
            Occurrence::create([
                'id' => $occ['id'],
                'user_id' => $previousShift->user_id,
                'shift_id' => $previousShift->id,
                'title' => $occ['title'],
                'category' => $occ['category'],
                'priority' => $occ['priority'],
                'status' => $occ['status'],
                'description' => $occ['description'],
                'location' => $occ['location'],
                'created_at' => $occ['created_at'],
                'updated_at' => Carbon::parse($occ['created_at'])->addHours(1),
            ]);
        }

        foreach ($currentShiftItems as $occ) {
            Occurrence::create([
                'id' => $occ['id'],
                'user_id' => $currentShift->user_id,
                'shift_id' => $currentShift->id,
                'title' => $occ['title'],
                'category' => $occ['category'],
                'priority' => $occ['priority'],
                'status' => $occ['status'],
                'description' => $occ['description'],
                'location' => $occ['location'],
                'comments' => [[
                    'id' => 'sys-' . uniqid(),
                    'author' => 'Sistema',
                    'text' => 'Ocorrencia criada no turno ativo para demonstracao.',
                    'type' => 'Sistema',
                    'createdAt' => now()->toISOString(),
                ]],
                'created_at' => $occ['created_at'],
                'updated_at' => $occ['created_at'],
            ]);
        }
    }
}
