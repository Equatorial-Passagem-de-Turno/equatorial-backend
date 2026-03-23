<?php

namespace App\Http\Controllers;

use App\Application\Services\Shift\StartShiftService;
use App\Application\Services\Shift\FinishShiftService;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use DomainException;
use Exception;
use Carbon\Carbon;

class ShiftController extends Controller
{
    public function reopen(Request $request): JsonResponse
    {
        $shift = Shift::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'finished')
            ->orderByDesc('end')
            ->first();

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum turno finalizado disponível para reabertura.',
            ], 404);
        }

        if (Shift::query()->where('user_id', $request->user()->id)->where('status', 'in_progress')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Já existe um turno em andamento para este operador.',
            ], 400);
        }

        $shift->status = 'in_progress';
        $shift->end = null;
        $shift->save();

        return response()->json([
            'success' => true,
            'message' => 'Turno reaberto com sucesso.',
            'data' => [
                'id' => $shift->id,
                'status' => $shift->status,
                'start' => optional($shift->start)->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function sendFinishEmail(Request $request, Shift $shift): JsonResponse
    {
        $request->validate([
            'recipientIds' => 'required|array|min:1',
            'recipientIds.*' => 'integer|exists:users,id',
            'summary' => 'nullable|array',
            'summary.resolvedCount' => 'nullable|integer',
            'summary.handoverCount' => 'nullable|integer',
            'summary.briefing' => 'nullable|string',
        ]);

        if ((int) $shift->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para notificar este turno.',
            ], 403);
        }

        if ($shift->status !== 'finished') {
            return response()->json([
                'success' => false,
                'message' => 'O turno precisa estar encerrado para envio de notificação.',
            ], 400);
        }

        $recipients = User::query()
            ->whereIn('id', $request->input('recipientIds', []))
            ->where('active', true)
            ->get(['id', 'name', 'email']);

        if ($recipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nenhum destinatário válido encontrado.',
            ], 400);
        }

        $resolvedCount = (int) data_get($request->input('summary', []), 'resolvedCount', 0);
        $handoverCount = (int) data_get($request->input('summary', []), 'handoverCount', 0);
        $briefing = (string) data_get($request->input('summary', []), 'briefing', '');

        $operatorName = $request->user()->name;
        $shiftId = $shift->id;
        $startedAt = optional($shift->start)?->format('d/m/Y H:i') ?? '--';
        $endedAt = optional($shift->end)?->format('d/m/Y H:i') ?? '--';

        foreach ($recipients as $recipient) {
            Mail::raw(
                "Encerramento de turno\n\n" .
                "Operador: {$operatorName}\n" .
                "Turno ID: {$shiftId}\n" .
                "Início: {$startedAt}\n" .
                "Fim: {$endedAt}\n" .
                "Pendências resolvidas: {$resolvedCount}\n" .
                "Pendências repassadas: {$handoverCount}\n\n" .
                "Briefing final:\n{$briefing}",
                function ($message) use ($recipient, $shiftId) {
                    $message->to($recipient->email, $recipient->name)
                        ->subject("Turno {$shiftId} encerrado");
                }
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Notificação enviada com sucesso.',
            'sent' => $recipients->count(),
        ]);
    }

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
                    'code' => $shift->desk->code,
                    'location' => $shift->desk->location,
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
