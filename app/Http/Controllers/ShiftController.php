<?php

namespace App\Http\Controllers;

use App\Application\Services\Shift\StartShiftService;
use App\Application\Services\Shift\FinishShiftService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;
use Exception;
use Carbon\Carbon;

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

    public function finish(Request $request, FinishShiftService $service): JsonResponse
    {
        $request->validate([
            'briefing' => 'required|string|min:10',
            'proximoOperador' => 'nullable|string',
            'pendenciasResolvidas' => 'nullable|array'
        ]);

        try {
            $shift = $service->execute(
                $request->user()->id,
                $request->input('briefing'),
                $request->input('proximoOperador'),
                $request->input('pendenciasResolvidas', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Turno finalizado com sucesso.',
                'data' => [
                    'id' => $shift->id,
                    'status' => $shift->status,
                    'end' => $shift->end ? $shift->end->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getCurrentShift(Request $request): JsonResponse
    {
        $shift = \App\Models\Shift::with(['desk', 'occurrences' => function($query) {
            $query->where('status', '!=', 'resolved');
        }])
        ->where('user_id', $request->user()->id)
        ->where('status', 'in_progress')
        ->first();

        if (!$shift) {
            return response()->json(null, 200);
        }

        $herdadas = [];
        $deixadas = [];

        if ($shift->occurrences) {
            foreach ($shift->occurrences as $occ) {
                $item = [
                    'id' => $occ->id,
                    'descricao' => $occ->title,
                    'prioridade' => $occ->priority,
                ];

                if ($occ->created_at < $shift->start) {
                    $herdadas[] = $item;
                } else {
                    $deixadas[] = $item;
                }
            }
        }

        return response()->json([
            'id' => $shift->id,
            'operador' => $request->user()->name,
            'funcao' => $shift->role,
            'inicio' => $shift->start ? $shift->start->format('H:i') : null,
            'data' => $shift->start ? $shift->start->format('d/m/Y') : null,
            'briefing' => $shift->briefing ?? '',
            'pendenciasHerdadas' => $herdadas,
            'pendenciasDeixadas' => $deixadas
        ]);
    }

    public function getPreviousShift(Request $request)
    {
        $currentShift = \App\Models\Shift::where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$currentShift || $currentShift->handover_acknowledged) {
            return response()->json(['occurrences' => []], 200);
        }

        if (!$currentShift) {
            return response()->json(['message' => 'Nenhum turno ativo'], 404);
        }

        $pendingOccurrences = \App\Models\Occurrence::with(['shift.user'])
            ->whereHas('shift', function ($query) use ($currentShift) {
                $query->where('operation_desk_id', $currentShift->operation_desk_id);
            })
            ->whereNotIn('status', ['resolved', 'finished', 'Resolvida', 'Finalizada'])
            ->where('created_at', '<', $currentShift->start)
            ->get();

        if ($pendingOccurrences->isEmpty()) {
            return response()->json(['message' => 'Nenhuma pendência para herdar'], 404);
        }

        $lastShift = null;
        if ($currentShift->previous_shift_id) {
            $lastShift = \App\Models\Shift::with(['user'])->find($currentShift->previous_shift_id);
        }

        $mappedOccurrences = $pendingOccurrences->map(function ($occ) {
            return [
                'id' => $occ->id,
                'title' => $occ->title ?? 'Sem Título',
                'description' => $occ->description ?? '',
                'priority' => $occ->priority, 
                'status' => $occ->status,
                'category' => $occ->category ?? 'Geral',
                'location' => $occ->location ?? 'Local não informado',
                'reportedBy' => $occ->shift->user->name ?? 'Operador Anterior', 
                'timestamp' => $occ->created_at ? $occ->created_at->format('H:i') : '--:--',
                'createdAt' => $occ->created_at ? $occ->created_at->format('d/m/Y H:i') : '--',
                'linkType' => $occ->link_type ?? null,
                'linkValue' => $occ->link_value ?? null,
            ];
        });

        $startFormat = $lastShift && $lastShift->start ? $lastShift->start->format('H:i') : '--:--';
        $endFormat = $lastShift && $lastShift->end ? $lastShift->end->format('H:i') : '--:--';
        $dateFormat = $lastShift && $lastShift->start ? $lastShift->start->format('d/m/Y') : date('d/m/Y');

        return response()->json([
            'currentShiftId' => $currentShift->id,
            'previousOperator' => $lastShift->user->name ?? 'Múltiplos Operadores',
            'shiftTime' => $startFormat . ' - ' . $endFormat,
            'date' => $dateFormat,
            'reportText' => $lastShift->briefing ?? 'Pendências acumuladas na mesa.',
            'criticalCount' => $mappedOccurrences->where('priority', 'crítica')->count(),
            'occurrences' => $mappedOccurrences->values()
        ]);
    }

    public function getShiftsByDate(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $shifts = \App\Models\Shift::with(['user'])
            ->whereDate('start', $date)
            ->orderBy('start', 'desc')
            ->get();

        $mappedShifts = $shifts->map(function ($shift) {
            return [
                'id' => 'TUR-' . str_pad($shift->id, 4, '0', STR_PAD_LEFT),
                'operador' => $shift->user->name ?? 'Desconhecido',
                'horario' => ($shift->start ? $shift->start->format('H:i') : '--:--') . ' - ' . ($shift->end ? $shift->end->format('H:i') : '...'),
                'tipo' => $shift->role ?? 'MT',
                'status' => $shift->status === 'in_progress' ? 'Aberto' : 'Fechado'
            ];
        });

        return response()->json($mappedShifts);
    }

    public function getPreviousShiftDetails(Request $request)
    {
        $currentShift = \App\Models\Shift::where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$currentShift || !$currentShift->previous_shift_id) {
            return response()->json(['message' => 'Nenhum turno anterior para visualizar'], 404);
        }

        $lastShift = \App\Models\Shift::with(['user', 'occurrences'])->find($currentShift->previous_shift_id);

        if (!$lastShift) {
            return response()->json(['message' => 'Turno não encontrado'], 404);
        }

        $mappedOccurrences = $lastShift->occurrences->map(function ($occ) use ($lastShift) {

            $statusReal = $occ->status;

            if (in_array($occ->status, ['Resolvida', 'Finalizada', 'resolved', 'finished'])) {
                if ($occ->updated_at > $lastShift->end) {
                    $statusReal = 'Aberta';
                }
            }

            return [
                'id' => $occ->id,
                'title' => $occ->title ?? 'Sem Título',
                'description' => $occ->description ?? '',
                'priority' => $occ->priority,
                'status' => ucfirst($statusReal),
                'category' => $occ->category ?? 'Geral',
                'location' => $occ->location ?? 'Local não informado',
                'reportedBy' => $lastShift->user->name ?? 'Operador Anterior',
                'timestamp' => $occ->created_at ? $occ->created_at->format('H:i') : '--:--',
                'createdAt' => $occ->created_at ? $occ->created_at->format('d/m/Y H:i') : '--',
                'linkType' => $occ->link_type ?? null,
                'linkValue' => $occ->link_value ?? null,
            ];
        });

        $startFormat = $lastShift->start ? $lastShift->start->format('H:i') : '00:00';
        $endFormat = $lastShift->end ? $lastShift->end->format('H:i') : '...';
        $dateFormat = $lastShift->start ? $lastShift->start->format('d/m/Y') : date('d/m/Y');

        return response()->json([
            'currentShiftId' => $currentShift->id,
            'previousOperator' => $lastShift->user->name ?? 'Operador Desconhecido',
            'shiftTime' => $startFormat . ' - ' . $endFormat,
            'date' => $dateFormat,
            'reportText' => $lastShift->briefing ?? 'Sem relatório inserido.',
            'criticalCount' => $mappedOccurrences->where('priority', 'crítica')->where('status', 'Aberta')->count(),
            'occurrences' => $mappedOccurrences->values()
        ]);
    }
}
