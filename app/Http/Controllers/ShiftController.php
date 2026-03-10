<?php

namespace App\Http\Controllers;

use App\Application\Services\Shift\StartShiftService;
use App\Application\Services\Shift\FinishShiftService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;
use Exception;

class ShiftController extends Controller
{

    public function start(Request $request, StartShiftService $service): JsonResponse 
        {
            $request->validate([
                'operation_desk_id' => 'required|exists:operation_desks,id',
                'role'              => 'required|string',
            ]);

            try {
                $shift = $service->execute(
                    $request->user()->id,
                    $request->input('operation_desk_id'),
                    $request->input('role')
                );

                return response()->json([
                    'id' => $shift->id,
                    'status' => $shift->status,
                    'start' => $shift->start->format('Y-m-d H:i:s'),
                    'role' => $shift->role,
                    'desk' => [
                        'id' => $shift->desk->id,
                        'name' => $shift->desk->name,
                    ]
                ], 201);

            } catch (Exception $e) {
                return response()->json([
                    'error' => $e->getMessage()
                ], 400);
            }
        }

    public function finish(Request $request, FinishShiftService $service): JsonResponse {
        $request->validate([
            'briefing' => 'required|string|min:10',
            'proximoOperador' => 'nullable|string',
            'pendenciasResolvidas' => 'nullable|array'
        ]);

        try {
            $userId = $request->user()->id;

            $shift = $service->execute(
                $userId,
                $request->input('briefing'),
                $request->input('proximoOperador'),
                $request->input('pendenciasResolvidas', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Shift finished successfully.',
                'data' => [
                    'id' => $shift->id,
                    'status' => $shift->status->value,
                    'end' => $shift->end->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (DomainException | Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getPreviousShift(Request $request)
    {
        $data = [
            'previousOperator' => 'JOÃO MENDES (MT)',
            'shiftTime' => '19:00 - 07:00',
            'date' => '04/12/2025',
            'reportText' => 'Turno com manobras programadas na região central. Atenção para pendências na SE-Centro.',
            'criticalCount' => 2,
            'occurrences' => [
                [
                    'id' => 'OC-202512-7327',
                    'title' => 'Manobra para manutenção preventiva',
                    'category' => 'Manobra Programada',
                    'priority' => 'crítica',
                    'status' => 'Em Andamento',
                    'createdAt' => '04/12/2025, 17:34:05',
                    'timestamp' => 'Há 20 min',
                    'reportedBy' => 'Operador 2',
                    'location' => [
                        'alimentador' => 'AL-03',
                        'subestacao' => 'SE-CENTRO',
                        'city' => 'MACEIO',
                        'neighborhood' => 'CENTRO',
                        'zone' => 'Urbana',
                        'address' => 'Rua do Comércio, 500',
                        'reference' => 'Ao lado do banco'
                    ],
                    'description' => 'Manobra iniciada conforme programação. Aguardando equipe de linha viva.',
                    'linkType' => 'OS',
                    'linkValue' => '1111',
                    'attachments' => [],
                    'comments' => [],
                    'reminders' => []
                ],
                [
                    'id' => 'OC-202512-7328',
                    'title' => 'Cabo Partido na via pública',
                    'category' => 'Emergencial',
                    'priority' => 'alta',
                    'status' => 'Pendente',
                    'createdAt' => '04/12/2025, 18:10:00',
                    'timestamp' => 'Há 10 min',
                    'reportedBy' => 'Call Center',
                    'location' => [
                        'alimentador' => 'AL-05',
                        'subestacao' => 'SE-TABULEIRO',
                        'city' => 'MACEIO',
                        'neighborhood' => 'TABULEIRO',
                        'address' => 'Av. Durval de Góes Monteiro',
                    ],
                    'description' => 'Popular informou cabo faiscando no chão. Risco iminente.',
                    'linkType' => 'OS',
                    'linkValue' => '1112',
                    'attachments' => [],
                    'comments' => [],
                    'reminders' => []
                ],
                // Adicione as outras ocorrências aqui com a mesma estrutura...
            ]
        ];

        return response()->json($data);
    }
}