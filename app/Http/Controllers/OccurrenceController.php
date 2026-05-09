<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

class OccurrenceController extends Controller
{
    private array $closedStatuses = ['resolved', 'finished', 'resolvida', 'finalizada', 'cancelada', 'fechada', 'encerrada', 'closed', 'cancelled', 'canceled', 'transferred'];

    private function isClosedStatus(string $status): bool
    {
        $normalized = strtolower(trim($status));
        return in_array($normalized, $this->closedStatuses, true);
    }

    private function normalizeOccurrencePayload(array $source): array
    {
        $payload = [];

        if (array_key_exists('title', $source)) {
            $payload['title'] = $source['title'];
        }

        if (array_key_exists('category', $source)) {
            $payload['category'] = $source['category'];
        }

        if (array_key_exists('priority', $source)) {
            $payload['priority'] = $source['priority'];
        }

        if (array_key_exists('status', $source)) {
            $payload['status'] = $source['status'];
        }

        if (array_key_exists('description', $source)) {
            $payload['description'] = $source['description'];
        }

        if (array_key_exists('location', $source)) {
            $payload['location'] = $source['location'];
        }

        if (array_key_exists('attachments', $source)) {
            $payload['attachments'] = $source['attachments'];
        }

        if (array_key_exists('comments', $source)) {
            $payload['comments'] = $source['comments'];
        }

        if (array_key_exists('reminders', $source)) {
            $payload['reminders'] = $source['reminders'];
        }

        if (array_key_exists('link_type', $source) || array_key_exists('linkType', $source)) {
            $payload['link_type'] = $source['link_type'] ?? $source['linkType'];
        }

        if (array_key_exists('link_value', $source) || array_key_exists('linkValue', $source)) {
            $payload['link_value'] = $source['link_value'] ?? $source['linkValue'];
        }

        return $payload;
    }

    private function mapOccurrenceForFrontend(Occurrence $occurrence, ?Shift $currentShift = null): array
    {
        $createdAt = $occurrence->created_at;
        $isInherited = false;
        $deskName = $occurrence->shift?->desk?->name;
        $createdBy = optional($occurrence->user)->name;

        if (!$createdBy) {
            $linkType = strtolower((string) $occurrence->link_type);
            $createdBy = $linkType === 'external' ? 'Externo' : 'Sistema';
        }

        if ($currentShift && $createdAt) {
            $isInherited = $createdAt->lt($currentShift->start);
        }

        $isStatusOpen = !$this->isClosedStatus((string) $occurrence->status);
        $isOpenForDashboard = $isStatusOpen;
        
        if ($currentShift) {
            $isOpenForDashboard = $isStatusOpen && ($occurrence->shift_id === $currentShift->id);
        }

        return [
            'id' => $occurrence->id,
            'user_id' => $occurrence->user_id,
            'authorId' => $occurrence->user_id,
            'shift_id' => $occurrence->shift_id,
            'title' => $occurrence->title,
            'category' => $occurrence->category,
            'priority' => $occurrence->priority,
            'status' => $occurrence->status,
            'description' => $occurrence->description,
            'location' => $occurrence->location,
            'operation_desk_name' => $deskName,
            'table' => $deskName,
            'link_type' => $occurrence->link_type,
            'linkType' => $occurrence->link_type,
            'link_value' => $occurrence->link_value,
            'linkValue' => $occurrence->link_value,
            'attachments' => $occurrence->attachments,
            'comments' => $occurrence->comments,
            'reminders' => $occurrence->reminders,
            'created_at' => $createdAt?->toISOString(),
            'updated_at' => $occurrence->updated_at?->toISOString(),
            'createdAt' => $createdAt ? $createdAt->format('d/m/Y H:i') : null,
            'createdBy' => $createdBy,
            'is_inherited' => $isInherited,
            'origin' => $isInherited ? 'Herdada' : 'Atual',
            'is_open' => $isOpenForDashboard,
        ];
    }

    public function storePublic(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'category' => 'nullable|string|max:255',
                'priority' => 'nullable|string|max:50',
                'status' => 'nullable|string|max:50',
                'location' => 'nullable|array',
                'linkType' => 'nullable|string|max:50',
                'link_type' => 'nullable|string|max:50',
                'linkValue' => 'nullable|string|max:255',
                'link_value' => 'nullable|string|max:255',
                'attachments' => 'nullable|array',
                'comments' => 'nullable|array',
                'reminders' => 'nullable|array',
            ]);

            $occurrenceId = $request->id ?? 'OC-' . date('Ym') . '-' . rand(1000, 9999);
            $normalizedPayload = $this->normalizeOccurrencePayload($validated);
            $comments = $normalizedPayload['comments'] ?? [];

            $comments[] = [
                'id' => 'sys-' . uniqid(),
                'author' => 'Sistema',
                'text' => 'Ocorrência externa registrada e encaminhada para supervisão.',
                'type' => 'Sistema',
                'createdAt' => now()->toISOString(),
            ];

            $occurrence = Occurrence::create([
                'id' => $occurrenceId,
                'user_id' => null,
                'shift_id' => null,
                'supervisor_id' => null,
                'title' => $normalizedPayload['title'],
                'category' => $normalizedPayload['category'] ?? 'Atendimento ao Cliente',
                'priority' => $normalizedPayload['priority'] ?? 'média',
                'status' => $normalizedPayload['status'] ?? 'Aberta',
                'description' => $normalizedPayload['description'],
                'location' => $normalizedPayload['location'] ?? null,
                'link_type' => 'External',
                'link_value' => $normalizedPayload['link_value'] ?? null,
                'attachments' => $normalizedPayload['attachments'] ?? null,
                'comments' => $comments,
                'reminders' => $normalizedPayload['reminders'] ?? null,
            ]);

            $occurrence->load(['user', 'shift.desk']);

            return response()->json([
                'success' => true,
                'message' => 'Occurrence registered successfully.',
                'data' => $this->mapOccurrenceForFrontend($occurrence, null),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $currentShift = \App\Models\Shift::query()
                ->select(['id', 'operation_desk_id', 'start', 'status'])
                ->where('user_id', $request->user()->id)
                ->where('status', 'in_progress')
                ->first();

            $accountRole = strtolower((string) $request->user()->role);
            $isSupervisor = in_array($accountRole, ['supervisor', 'admin', 'adm'], true);

            if (!$currentShift && !$isSupervisor) {
                return response()->json([]);
            }

            $maxItems = $isSupervisor ? 1200 : 800;
            $hasRemindersColumn = Schema::hasColumn('occurrences', 'reminders');

            $selectColumns = [
                'id',
                'user_id',
                'shift_id',
                'title',
                'category',
                'priority',
                'status',
                'description',
                'location',
                'link_type',
                'link_value',
                'attachments',
                'comments',
                'created_at',
                'updated_at',
            ];

            if ($hasRemindersColumn) {
                $selectColumns[] = 'reminders';
            }

            $occurrencesQuery = \App\Models\Occurrence::query()
                ->select($selectColumns)
                ->with([
                    'shift:id,operation_desk_id,end',
                    'shift.desk:id,name',
                    'user:id,name',
                ]);

            if ($currentShift) {
                $occurrencesQuery->whereHas('shift', function ($query) use ($currentShift) {
                    $query->where('operation_desk_id', $currentShift->operation_desk_id);
                });
            }

            if ($isSupervisor && !$currentShift) {
                $occurrencesQuery->where('created_at', '>=', now()->subDays(30));
            }

            $occurrences = $occurrencesQuery
                ->where(function($query) {
                    $query->whereNotIn('status', ['resolved', 'finished', 'closed', 'cancelled', 'canceled'])
                        ->orWhere('created_at', '>=', now()->subDays(15));
                })
                ->orderByDesc('id')
                ->limit($maxItems)
                ->get();

            $filteredOccurrences = $occurrences->filter(function ($occ) {
                $isClosed = $this->isClosedStatus((string) $occ->status);

                if ($isClosed && $occ->shift && $occ->shift->end) {
                    if ($occ->updated_at > $occ->shift->end) {
                        return false;
                    }
                }
                return true;
            });

            $mapped = $filteredOccurrences->values()->map(function ($occurrence) use ($currentShift) {
                return $this->mapOccurrenceForFrontend($occurrence, $currentShift);
            });

            return response()->json($mapped);
        } catch (\Throwable $exception) {
            Log::error('Falha ao listar ocorrencias', [
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([], 200);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'category' => 'required|string|max:255',
                'priority' => 'required|string|max:50',
                'status' => 'required|string|max:50',
                'description' => 'required|string',
                'location' => 'nullable|array',
                'linkType' => 'nullable|string|max:50',
                'link_type' => 'nullable|string|max:50',
                'linkValue' => 'nullable|string|max:255',
                'link_value' => 'nullable|string|max:255',
                'attachments' => 'nullable|array',
                'comments' => 'nullable|array',
                'reminders' => 'nullable|array',
                'assigned_operator_id' => 'nullable|integer|exists:users,id',
                'assigned_operation_desk_id' => 'nullable|integer|exists:operation_desks,id',
            ]);

            $accountRole = strtolower((string) $request->user()->role);
            $isSupervisor = in_array($accountRole, ['supervisor', 'admin', 'adm'], true);

            $currentShift = Shift::where('user_id', $request->user()->id)
                ->where('status', 'in_progress')
                ->first();

            $assignedOperatorId = $validated['assigned_operator_id'] ?? null;
            $assignedDeskId = $validated['assigned_operation_desk_id'] ?? null;
            $targetShift = $currentShift;

            if ($isSupervisor) {
                if (!$assignedOperatorId && !$assignedDeskId) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Selecione um operador ou uma mesa para direcionar a ocorrência.',
                    ], 422);
                }

                if ($assignedOperatorId && $assignedDeskId) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Selecione apenas um destino: operador ou mesa.',
                    ], 422);
                }

                if ($assignedOperatorId) {
                    $targetShift = Shift::query()
                        ->with(['desk:id,name', 'user:id,name'])
                        ->where('user_id', $assignedOperatorId)
                        ->where('status', 'in_progress')
                        ->orderByDesc('start')
                        ->first();

                    if (!$targetShift) {
                        return response()->json([
                            'success' => false,
                            'error' => 'O operador selecionado não possui turno em andamento para receber a ocorrência.',
                        ], 422);
                    }
                }

                if ($assignedDeskId) {
                    $targetShift = Shift::query()
                        ->with(['desk:id,name', 'user:id,name'])
                        ->where('operation_desk_id', $assignedDeskId)
                        ->where('status', 'in_progress')
                        ->orderByDesc('start')
                        ->first();

                    if (!$targetShift) {
                        return response()->json([
                            'success' => false,
                            'error' => 'A mesa selecionada não possui turno em andamento para receber a ocorrência.',
                        ], 422);
                    }
                }
            }

            $occurrenceId = $request->id ?? 'OC-' . date('Ym') . '-' . rand(1000, 9999);
            
            $normalizedPayload = $this->normalizeOccurrencePayload($validated);

            $comments = $normalizedPayload['comments'] ?? [];

            if ($isSupervisor) {
                if ($assignedOperatorId) {
                    $operatorName = $targetShift?->user?->name ?? 'Operador';
                    $deskName = $targetShift?->desk?->name ?? 'Mesa';
                    $comments[] = [
                        'id' => 'sys-' . uniqid(),
                        'author' => 'Sistema',
                        'text' => "Ocorrência criada pelo supervisor {$request->user()->name} e direcionada para o operador {$operatorName} ({$deskName}).",
                        'type' => 'Sistema',
                        'createdAt' => now()->toISOString(),
                    ];
                } else {
                    $deskName = $targetShift?->desk?->name ?? 'Mesa';
                    $operatorName = $targetShift?->user?->name ?? 'Operador';
                    $comments[] = [
                        'id' => 'sys-' . uniqid(),
                        'author' => 'Sistema',
                        'text' => "Ocorrência criada pelo supervisor {$request->user()->name} e direcionada para a mesa {$deskName} (operador atual: {$operatorName}).",
                        'type' => 'Sistema',
                        'createdAt' => now()->toISOString(),
                    ];
                }
            } else {
                $shiftInfo = $currentShift ? " no turno " . $currentShift->id : "";
                $comments[] = [
                    'id' => 'sys-' . uniqid(),
                    'author' => 'Sistema',
                    'text' => "Ocorrência criada pelo operador " . $request->user()->name . $shiftInfo,
                    'type' => 'Sistema',
                    'createdAt' => now()->toISOString(),
                ];
            }

            $occurrence = Occurrence::create([
                'id' => $occurrenceId,
                'user_id' => $isSupervisor
                    ? ($targetShift?->user_id ?? $assignedOperatorId)
                    : $request->user()->id,
                'shift_id' => $targetShift ? $targetShift->id : null,
                'supervisor_id' => $isSupervisor ? $request->user()->id : null,
                'title' => $normalizedPayload['title'],
                'category' => $normalizedPayload['category'],
                'priority' => $normalizedPayload['priority'],
                'status' => $normalizedPayload['status'],
                'description' => $normalizedPayload['description'],
                'location' => $normalizedPayload['location'] ?? null,
                'link_type' => $normalizedPayload['link_type'] ?? null,
                'link_value' => $normalizedPayload['link_value'] ?? null,
                'attachments' => $normalizedPayload['attachments'] ?? null,
                'comments' => $comments,
                'reminders' => $normalizedPayload['reminders'] ?? null,
            ]);

            $occurrence->load(['user', 'shift.desk']);

            return response()->json([
                'success' => true,
                'message' => 'Occurrence registered successfully.',
                'data' => $this->mapOccurrenceForFrontend($occurrence, $targetShift)
            ], 201);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function assign(Request $request, string $id): JsonResponse
    {
        $accountRole = strtolower((string) $request->user()->role);
        $isSupervisor = in_array($accountRole, ['supervisor', 'admin', 'adm'], true);

        if (!$isSupervisor) {
            return response()->json([
                'success' => false,
                'error' => 'Apenas supervisores podem designar ocorrências.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'assigned_operator_id' => 'nullable|integer|exists:users,id',
                'assigned_operation_desk_id' => 'nullable|integer|exists:operation_desks,id',
                'reason' => 'nullable|string|max:500',
            ]);

            $assignedOperatorId = $validated['assigned_operator_id'] ?? null;
            $assignedDeskId = $validated['assigned_operation_desk_id'] ?? null;

            if (!$assignedOperatorId && !$assignedDeskId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Selecione um operador ou uma mesa para direcionar a ocorrência.',
                ], 422);
            }

            if ($assignedOperatorId && $assignedDeskId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Selecione apenas um destino: operador ou mesa.',
                ], 422);
            }

            $targetShift = null;

            if ($assignedOperatorId) {
                $targetShift = Shift::query()
                    ->with(['desk:id,name', 'user:id,name'])
                    ->where('user_id', $assignedOperatorId)
                    ->where('status', 'in_progress')
                    ->orderByDesc('start')
                    ->first();

                if (!$targetShift) {
                    return response()->json([
                        'success' => false,
                        'error' => 'O operador selecionado não possui turno em andamento para receber a ocorrência.',
                    ], 422);
                }
            }

            if ($assignedDeskId) {
                $targetShift = Shift::query()
                    ->with(['desk:id,name', 'user:id,name'])
                    ->where('operation_desk_id', $assignedDeskId)
                    ->where('status', 'in_progress')
                    ->orderByDesc('start')
                    ->first();

                if (!$targetShift) {
                    return response()->json([
                        'success' => false,
                        'error' => 'A mesa selecionada não possui turno em andamento para receber a ocorrência.',
                    ], 422);
                }
            }

            $occurrence = Occurrence::with(['user', 'shift.desk'])->findOrFail($id);
            $comments = $occurrence->comments ?? [];
            $reason = trim((string) ($validated['reason'] ?? ''));

            $deskName = $targetShift?->desk?->name ?? 'Mesa';
            $operatorName = $targetShift?->user?->name ?? 'Operador';

            $commentText = "Ocorrência designada pelo supervisor {$request->user()->name} para {$operatorName} ({$deskName}).";
            if ($reason !== '') {
                $commentText .= " Motivo: {$reason}.";
            }

            $comments[] = [
                'id' => 'sys-' . uniqid(),
                'author' => 'Sistema',
                'text' => $commentText,
                'type' => 'Sistema',
                'createdAt' => now()->toISOString(),
            ];

            $occurrence->update([
                'user_id' => $targetShift?->user_id ?? $assignedOperatorId,
                'shift_id' => $targetShift?->id,
                'supervisor_id' => $request->user()->id,
                'comments' => $comments,
            ]);

            $occurrence->refresh();
            $occurrence->load(['user', 'shift.desk']);

            return response()->json([
                'success' => true,
                'data' => $this->mapOccurrenceForFrontend($occurrence, $targetShift),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function show($id): JsonResponse
    {
        $occurrence = Occurrence::with(['user', 'shift.desk'])->findOrFail($id);
        return response()->json($this->mapOccurrenceForFrontend($occurrence, $occurrence->shift));
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'category' => 'sometimes|string|max:255',
                'priority' => 'sometimes|string|max:50',
                'status' => 'sometimes|string|max:50',
                'description' => 'sometimes|string',
                'location' => 'sometimes|nullable|array',
                'linkType' => 'sometimes|nullable|string|max:50',
                'link_type' => 'sometimes|nullable|string|max:50',
                'linkValue' => 'sometimes|nullable|string|max:255',
                'link_value' => 'sometimes|nullable|string|max:255',
                'attachments' => 'sometimes|nullable|array',
                'comments' => 'sometimes|nullable|array',
                'reminders' => 'sometimes|nullable|array',
            ]);

            $occurrence = Occurrence::with(['user', 'shift.desk'])->findOrFail($id);
            $payload = $this->normalizeOccurrencePayload($validated);

            if (!empty($payload)) {
                $occurrence->update($payload);
            }

            $occurrence->refresh();
            $occurrence->load(['user', 'shift.desk']);

            return response()->json([
                'success' => true,
                'data' => $this->mapOccurrenceForFrontend($occurrence, $occurrence->shift)
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function appendComment(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'text' => 'required|string',
                'type' => 'nullable|string|max:50',
            ]);

            $occurrence = Occurrence::with(['user', 'shift.desk'])->findOrFail($id);
            $comments = is_array($occurrence->comments) ? $occurrence->comments : [];

            $comments = array_values($comments);
            array_unshift($comments, [
                'id' => 'cmt-' . uniqid(),
                'author' => $request->user()->name ?? 'Sistema',
                'text' => $validated['text'],
                'type' => $validated['type'] ?? 'Geral',
                'createdAt' => now()->toISOString(),
            ]);

            $occurrence->comments = $comments;
            $occurrence->save();

            $occurrence->refresh();
            $occurrence->load(['user', 'shift.desk']);

            return response()->json([
                'success' => true,
                'data' => $this->mapOccurrenceForFrontend($occurrence, $occurrence->shift),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function destroy($id): JsonResponse
    {
        $occurrence = Occurrence::findOrFail($id);
        $occurrence->delete();

        return response()->json(['success' => true, 'message' => 'Excluído com sucesso']);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'occurrences' => 'required|array|min:1',
                'occurrences.*.title' => 'required|string|max:255',
                'occurrences.*.category' => 'nullable|string|max:255',
                'occurrences.*.priority' => 'nullable|string|max:50',
                'occurrences.*.status' => 'nullable|string|max:50',
                'occurrences.*.description' => 'nullable|string',
                'occurrences.*.location' => 'nullable|array',
                'occurrences.*.comments' => 'nullable|array',
                'occurrences.*.reminders' => 'nullable|array',
                'occurrences.*.linkType' => 'nullable|string|max:50',
                'occurrences.*.linkValue' => 'nullable|string|max:255',
            ]);

            $occurrences = $request->input('occurrences');
            $userId = $request->user()->id;

            $currentShift = \App\Models\Shift::where('user_id', $userId)
                                ->where('status', 'in_progress')
                                ->first();

            if (!$currentShift) {
                return response()->json([
                    'success' => false,
                    'error' => 'É necessário ter um turno em andamento para assumir pendências.',
                ], 400);
            }

            $currentShift->update(['handover_acknowledged' => true]);

            $createdOccurrences = [];

            foreach ($occurrences as $data) {
                $oldId = $data['id'] ?? null;
                $newId = 'OC-' . date('Ym') . '-' . rand(1000, 9999);

                $oldOccurrence = $oldId ? Occurrence::find($oldId) : null;
                $commentsHist = $oldOccurrence && $oldOccurrence->comments ? $oldOccurrence->comments : [];

                if (isset($data['comments']) && is_array($data['comments'])) {
                    $commentsHist = array_merge($commentsHist, $data['comments']);
                }

                $shiftInfo = $currentShift ? " no turno " . $currentShift->id : "";
                
                $commentsHist[] = [
                    'id' => 'sys-' . uniqid(),
                    'author' => 'Sistema',
                    'text' => "Ocorrência assumida na troca de turno pelo operador " . $request->user()->name . $shiftInfo,
                    'type' => 'Sistema',
                    'createdAt' => now()->toISOString()
                ];

                $newOcc = Occurrence::create([
                    'id' => $newId,
                    'user_id' => $userId,
                    'shift_id' => $currentShift->id,
                    'title' => $data['title'] ?? 'Sem Título',
                    'category' => $data['category'] ?? 'Herdada de Turno',
                    'priority' => $data['priority'] ?? 'medium',
                    'status' => 'open',
                    'description' => $data['description'] ?? '', 
                    'location' => $data['location'] ?? null,
                    'link_type' => $data['linkType'] ?? ($data['link_type'] ?? null),
                    'link_value' => $data['linkValue'] ?? ($data['link_value'] ?? null),
                    'comments' => $commentsHist,
                    'reminders' => $data['reminders'] ?? null,
                ]);

                $newOcc->load(['user', 'shift.desk']);
                $createdOccurrences[] = $this->mapOccurrenceForFrontend($newOcc, $currentShift);

                \Illuminate\Support\Facades\DB::table('occurrences')
                    ->where('id', $newId)
                    ->update(['created_at' => (clone $currentShift->start)->subMinutes(5)]);

                if ($oldId) {
                    Occurrence::where('id', $oldId)->update(['status' => 'transferred']);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $createdOccurrences,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
