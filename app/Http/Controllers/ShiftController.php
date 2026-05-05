<?php

namespace App\Http\Controllers;

use App\Application\Services\Shift\StartShiftService;
use App\Application\Services\Shift\FinishShiftService;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use DomainException;
use Exception;
use Carbon\Carbon;

class ShiftController extends Controller
{
    private const BRAZILIA_TIMEZONE = 'America/Sao_Paulo';

    private function formatInBrasilia(?Carbon $dateTime, string $format, ?string $fallback = null): ?string
    {
        if (!$dateTime) {
            return $fallback;
        }

        return $dateTime->copy()->setTimezone(self::BRAZILIA_TIMEZONE)->format($format);
    }

    private function nowInBrasilia(string $format): string
    {
        return now()->setTimezone(self::BRAZILIA_TIMEZONE)->format($format);
    }

    private function formatWorkedDurationFromMinutes(int $minutes): string
    {
        $safeMinutes = max(0, $minutes);
        $hours = intdiv($safeMinutes, 60);
        $remainingMinutes = $safeMinutes % 60;

        return sprintf('%dh %02dm', $hours, $remainingMinutes);
    }

    private function calculateWorkedMinutes(?Carbon $start, ?Carbon $end = null): int
    {
        if (!$start) {
            return 0;
        }

        $effectiveEnd = $end ?: now();
        if ($effectiveEnd->lt($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($effectiveEnd);
    }

    private function getWorkedDurationPayload(Shift $shift): array
    {
        $workedMinutes = $this->calculateWorkedMinutes($shift->start, $shift->end);
        $workedDuration = $this->formatWorkedDurationFromMinutes($workedMinutes);

        return [
            'tempo_trabalhado_minutos' => $workedMinutes,
            'tempo_trabalhado' => $workedDuration,
            'workedMinutes' => $workedMinutes,
            'workedDuration' => $workedDuration,
        ];
    }

    private function isClosedOccurrenceStatus(?string $status): bool
    {
        $normalized = strtolower(trim((string) $status));
        return in_array($normalized, ['resolved', 'finished', 'resolvida', 'finalizada', 'cancelada', 'fechada', 'encerrada', 'closed', 'cancelled', 'canceled'], true);
    }

    private function formatShiftProfileLabel(?string $role, ?string $voltage): string
    {
        $profile = strtoupper(trim($this->normalizeOperatorProfile($role, $voltage)));

        return match ($profile) {
            'BT' => 'Baixa Tensão (BT)',
            'MT' => 'Média Tensão (MT)',
            'AT' => 'Alta Tensão (AT)',
            'ENG. PRÉ-OP', 'ENG. PRE-OP', 'ENG. PRE OP' => 'Eng. Pré-Op',
            default => $profile !== '' ? $profile : 'BT',
        };
    }

    private function normalizeOperatorProfile(?string $role, ?string $voltage): string
    {
        $roleValue = (string) $role;
        if (preg_match('/\(([^)]+)\)/', $roleValue, $matches) === 1) {
            return strtoupper(trim($matches[1]));
        }

        $voltageValue = strtoupper(trim((string) $voltage));
        if ($voltageValue !== '') {
            return $voltageValue;
        }

        return 'BT';
    }

    public function getActiveOperatorsSummary(Request $request): JsonResponse
    {
        try {
            $activeShifts = Shift::query()
                ->whereHas('user', function ($query) {
                    $query->where('active', true)
                        ->whereRaw('LOWER(role) = ?', ['operador']);
                })
                ->with([
                    'user:id,name,email,role,voltage_level,active',
                    'desk:id,code,name',
                    'occurrences:id,shift_id,status,created_at',
                ])
                ->where('status', 'in_progress')
                ->orderByDesc('start')
                ->get();

            $rows = $activeShifts
                ->map(function (Shift $shift) {
                    if (!$shift->user) {
                        return null;
                    }

                    $all = $shift->occurrences ?? collect();

                    $resolved = $all->filter(fn ($occ) => $this->isClosedOccurrenceStatus((string) $occ->status))->count();

                    $open = $all->filter(fn ($occ) => !$this->isClosedOccurrenceStatus((string) $occ->status));
                    $inherited = $open->filter(fn ($occ) => $shift->start && $occ->created_at && $occ->created_at->lt($shift->start))->count();
                    $created = $open->filter(fn ($occ) => !$shift->start || !$occ->created_at || $occ->created_at->gte($shift->start))->count();

                    return [
                        'id' => (string) $shift->user->id,
                        'name' => $shift->user->name,
                        'email' => $shift->user->email,
                        'profile' => $this->normalizeOperatorProfile($shift->role, $shift->user->voltage_level),
                        'table_id' => $shift->operation_desk_id ? (int) $shift->operation_desk_id : null,
                        'table' => $shift->desk?->name ?? 'N/A',
                        'table_code' => $shift->desk?->code,
                        'status' => 'Ativo',
                        'inherited_occurrences' => $inherited,
                        'created_occurrences' => $created,
                        'resolved_occurrences' => $resolved,
                        'assumed_occurrences' => $inherited + $created + $resolved,
                    ];
                })
                ->filter()
                ->values();

            return response()->json($rows);
        } catch (\Throwable $exception) {
            Log::error('Falha ao listar operadores ativos', [
                'request_user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([], 200);
        }
    }

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
                'start' => $this->formatInBrasilia($shift->start, 'Y-m-d H:i:s'),
                ...$this->getWorkedDurationPayload($shift),
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
        $startedAt = $this->formatInBrasilia($shift->start, 'd/m/Y H:i', '--');
        $endedAt = $this->formatInBrasilia($shift->end, 'd/m/Y H:i', '--');

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
                'start' => $this->formatInBrasilia($shift->start, 'Y-m-d H:i:s'),
                'role' => $shift->role,
                'desk' => [
                    'id' => $shift->desk->id,
                    'name' => $shift->desk->name,
                    'code' => $shift->desk->code,
                    'location' => $shift->desk->location,
                ],
                ...$this->getWorkedDurationPayload($shift),
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
                    'end' => $shift->end
                        ? $this->formatInBrasilia($shift->end, 'Y-m-d H:i:s')
                        : $this->nowInBrasilia('Y-m-d H:i:s'),
                    ...$this->getWorkedDurationPayload($shift),
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
        try {
            $shift = \App\Models\Shift::query()
            ->select(['id', 'user_id', 'operation_desk_id', 'role', 'start', 'end', 'status', 'briefing'])
            ->with([
                'desk:id,name,code',
                'occurrences' => function($query) {
                    $query
                        ->select(['id', 'shift_id', 'title', 'priority', 'status', 'created_at'])
                        ->whereNotIn('status', ['resolved', 'finished', 'closed', 'cancelled', 'canceled']);
                }
            ])
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

                    $isInherited = $shift->start && $occ->created_at
                        ? $occ->created_at->lt($shift->start)
                        : false;

                    if ($isInherited) {
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
                'inicio' => $this->formatInBrasilia($shift->start, 'H:i'),
                'data' => $this->formatInBrasilia($shift->start, 'd/m/Y'),
                'start_utc' => $shift->start ? $shift->start->toISOString() : null,
                'briefing' => $shift->briefing ?? '',
                'pendenciasHerdadas' => $herdadas,
                'pendenciasDeixadas' => $deixadas,
                ...$this->getWorkedDurationPayload($shift),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Falha ao carregar turno atual', [
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(null, 200);
        }
    }

    public function getPreviousShift(Request $request)
    {
        try {
            $currentShift = \App\Models\Shift::where('user_id', $request->user()->id)
                ->where('status', 'in_progress')
                ->first();

            if (!$currentShift || $currentShift->handover_acknowledged) {
                return response()->json(['occurrences' => []], 200);
            }

            $pendingOccurrences = \App\Models\Occurrence::with(['shift.user'])
                ->whereHas('shift', function ($query) use ($currentShift) {
                    $query->where('operation_desk_id', $currentShift->operation_desk_id);
                })
                ->whereNotIn('status', ['resolved', 'finished', 'closed', 'cancelled', 'canceled'])
                ->where('created_at', '<', $currentShift->start)
                ->get();

            if ($pendingOccurrences->isEmpty()) {
                return response()->json(['occurrences' => []], 200);
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
                    'reportedBy' => $occ->shift?->user?->name ?? 'Operador Anterior',
                    'timestamp' => $this->formatInBrasilia($occ->created_at, 'H:i', '--:--'),
                    'createdAt' => $this->formatInBrasilia($occ->created_at, 'd/m/Y H:i', '--'),
                    'linkType' => $occ->link_type ?? null,
                    'linkValue' => $occ->link_value ?? null,
                ];
            });

            $startFormat = $lastShift ? $this->formatInBrasilia($lastShift->start, 'H:i', '--:--') : '--:--';
            $endFormat = $lastShift ? $this->formatInBrasilia($lastShift->end, 'H:i', '--:--') : '--:--';
            $dateFormat = $lastShift
                ? $this->formatInBrasilia($lastShift->start, 'd/m/Y', $this->nowInBrasilia('d/m/Y'))
                : $this->nowInBrasilia('d/m/Y');

            return response()->json([
                'currentShiftId' => $currentShift->id,
                'previousOperator' => $lastShift?->user?->name ?? 'Múltiplos Operadores',
                'shiftTime' => $startFormat . ' - ' . $endFormat,
                'date' => $dateFormat,
                'reportText' => $lastShift?->briefing ?? 'Pendências acumuladas na mesa.',
                'criticalCount' => $mappedOccurrences->where('priority', 'crítica')->count(),
                'occurrences' => $mappedOccurrences->values(),
                'tempoTrabalhadoTurnoAnterior' => $lastShift ? $this->getWorkedDurationPayload($lastShift)['tempo_trabalhado'] : '0h 00m',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Falha ao carregar handover do turno anterior', [
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['occurrences' => []], 200);
        }
    }

    public function getShiftsByDate(Request $request): JsonResponse
    {
        try {
            $date = $request->query('date', now()->toDateString());

            $shifts = \App\Models\Shift::query()
                ->select(['id', 'user_id', 'role', 'start', 'end', 'status'])
                ->with(['user:id,name,voltage_level'])
                ->whereDate('start', $date)
                ->orderBy('start', 'desc')
                ->get();

            $mappedShifts = $shifts->map(function ($shift) {
                $worked = $this->getWorkedDurationPayload($shift);
                $voltageLevel = $shift->user?->voltage_level;

                return [
                    'shift_id' => (int) $shift->id,
                    'shiftId' => (int) $shift->id,
                    'id' => 'TUR-' . str_pad((string) $shift->id, 4, '0', STR_PAD_LEFT),
                    'operador' => $shift->user?->name ?? 'Desconhecido',
                    'horario' => $this->formatInBrasilia($shift->start, 'H:i', '--:--') . ' - ' . $this->formatInBrasilia($shift->end, 'H:i', '...'),
                    'tipo' => $this->formatShiftProfileLabel($shift->role, $voltageLevel),
                    'tipo_code' => $this->normalizeOperatorProfile($shift->role, $voltageLevel),
                    'status' => $shift->status === 'in_progress' ? 'Aberto' : 'Fechado',
                    'tempo_trabalhado_minutos' => $worked['tempo_trabalhado_minutos'],
                    'tempo_trabalhado' => $worked['tempo_trabalhado'],
                    'workedMinutes' => $worked['workedMinutes'],
                    'workedDuration' => $worked['workedDuration'],
                ];
            });

            return response()->json($mappedShifts);
        } catch (\Throwable $exception) {
            Log::error('Falha ao listar turnos por data', [
                'user_id' => $request->user()?->id,
                'date' => $request->query('date'),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([], 200);
        }
    }

    public function getShiftsByUser(Request $request, int $userId): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 120));

        $shifts = \App\Models\Shift::query()
            ->where('user_id', $userId)
            ->where('start', '>=', now()->subDays($days))
            ->orderByDesc('start')
            ->get();

        $mapped = $shifts->map(function ($shift) {
            $worked = $this->getWorkedDurationPayload($shift);

            return [
                'id' => 'TUR-' . str_pad((string) $shift->id, 4, '0', STR_PAD_LEFT),
                'date' => $this->formatInBrasilia($shift->start, 'd/m/Y', '--/--/----'),
                'time' => $this->formatInBrasilia($shift->start, 'H:i', '--:--') . ' - ' . $this->formatInBrasilia($shift->end, 'H:i', '...'),
                'status' => $shift->status,
                'tempo_trabalhado_minutos' => $worked['tempo_trabalhado_minutos'],
                'tempo_trabalhado' => $worked['tempo_trabalhado'],
                'workedMinutes' => $worked['workedMinutes'],
                'workedDuration' => $worked['workedDuration'],
            ];
        })->values();

        return response()->json($mapped);
    }

    public function show(Shift $shift): JsonResponse
    {
        $shift->load(['user:id,name,email,role,voltage_level', 'desk:id,code,name,location', 'occurrences' => function ($query) {
            $query->orderByDesc('created_at');
        }]);

        $occurrences = $shift->occurrences->map(function ($occurrence) use ($shift) {
            $comments = is_array($occurrence->comments) ? $occurrence->comments : [];
            $locationValue = $occurrence->location;

            return [
                'id' => $occurrence->id,
                'title' => $occurrence->title ?? 'Sem título',
                'description' => $occurrence->description ?? '',
                'category' => $occurrence->category ?? 'Geral',
                'priority' => $occurrence->priority,
                'status' => $occurrence->status,
                'location' => is_array($locationValue) ? ($locationValue['name'] ?? $locationValue['label'] ?? 'Local não informado') : ($locationValue ?: 'Local não informado'),
                'linkType' => $occurrence->link_type,
                'linkValue' => $occurrence->link_value,
                'createdAt' => $this->formatInBrasilia($occurrence->created_at, 'd/m/Y H:i'),
                'updatedAt' => $this->formatInBrasilia($occurrence->updated_at, 'd/m/Y H:i'),
                'commentsCount' => count($comments),
                'comments' => $comments,
                'origin' => $occurrence->created_at && $shift->start && $occurrence->created_at->lt($shift->start) ? 'Herdada' : 'Atual',
                'isOpen' => !$this->isClosedOccurrenceStatus((string) $occurrence->status),
            ];
        })->values();

        $openedOccurrences = $occurrences->filter(fn ($occurrence) => $occurrence['isOpen'])->count();
        $resolvedOccurrences = $occurrences->count() - $openedOccurrences;
        $worked = $this->getWorkedDurationPayload($shift);

        return response()->json([
            'id' => (int) $shift->id,
            'displayId' => 'TUR-' . str_pad($shift->id, 4, '0', STR_PAD_LEFT),
            'operador' => $shift->user->name ?? 'Desconhecido',
            'email' => $shift->user->email ?? null,
            'funcao' => $this->normalizeOperatorProfile($shift->role, $shift->user->voltage_level ?? null),
            'funcaoLabel' => $this->formatShiftProfileLabel($shift->role, $shift->user->voltage_level ?? null),
            'mesa' => $shift->desk?->name ?? 'Mesa não informada',
            'mesaCode' => $shift->desk?->code,
            'start' => $this->formatInBrasilia($shift->start, 'd/m/Y H:i'),
            'end' => $this->formatInBrasilia($shift->end, 'd/m/Y H:i'),
            'status' => $shift->status === 'in_progress' ? 'Aberto' : 'Fechado',
            'briefing' => $shift->briefing ?? '',
            'totalOccurrences' => $occurrences->count(),
            'openOccurrences' => $openedOccurrences,
            'resolvedOccurrences' => $resolvedOccurrences,
            'occurrences' => $occurrences,
            'tempo_trabalhado_minutos' => $worked['tempo_trabalhado_minutos'],
            'tempo_trabalhado' => $worked['tempo_trabalhado'],
            'workedMinutes' => $worked['workedMinutes'],
            'workedDuration' => $worked['workedDuration'],
        ]);
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
            $rawStatus = $occ->getRawOriginal('status'); // bypassa o accessor

            $statusReal = match($rawStatus) {
                'open'        => 'Aberta',
                'in_progress' => 'Em Andamento',
                'resolved'    => 'Resolvida',
                'finished'    => 'Finalizada',
                'transferred' => 'Aberta', // assumida = ainda era pendente quando o turno acabou
                default       => ucfirst($rawStatus),
            };

            return [
                'id'          => $occ->id,
                'title'       => $occ->title ?? 'Sem Título',
                'description' => $occ->description ?? '',
                'priority'    => $occ->priority,
                'status'      => $statusReal,
                'category'    => $occ->category ?? 'Geral',
                'location'    => $occ->location ?? 'Local não informado',
                'reportedBy'  => $lastShift->user->name ?? 'Operador Anterior',
                'timestamp'   => $this->formatInBrasilia($occ->created_at, 'H:i', '--:--'),
                'createdAt'   => $this->formatInBrasilia($occ->created_at, 'd/m/Y H:i', '--'),
                'linkType'    => $occ->link_type ?? null,
                'linkValue'   => $occ->link_value ?? null,
            ];
        });

        $startFormat = $this->formatInBrasilia($lastShift->start, 'H:i', '00:00');
        $endFormat = $this->formatInBrasilia($lastShift->end, 'H:i', '...');
        $dateFormat = $this->formatInBrasilia($lastShift->start, 'd/m/Y', $this->nowInBrasilia('d/m/Y'));

        return response()->json([
            'currentShiftId' => $currentShift->id,
            'previousOperator' => $lastShift->user->name ?? 'Operador Desconhecido',
            'shiftTime' => $startFormat . ' - ' . $endFormat,
            'date' => $dateFormat,
            'reportText' => $lastShift->briefing ?? 'Sem relatório inserido.',
            'criticalCount' => $mappedOccurrences->where('priority', 'crítica')->where('status', 'Aberta')->count(),
            'occurrences' => $mappedOccurrences->values(),
            'tempoTrabalhadoTurnoAnterior' => $this->getWorkedDurationPayload($lastShift)['tempo_trabalhado'],
        ]);
    }
}
