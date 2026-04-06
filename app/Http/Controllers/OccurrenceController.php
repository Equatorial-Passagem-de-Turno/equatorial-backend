<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class OccurrenceController extends Controller
{
    private array $closedStatuses = ['resolved', 'finished', 'resolvida', 'finalizada', 'cancelada', 'fechada', 'encerrada', 'closed', 'cancelled', 'canceled'];

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

        if ($currentShift && $createdAt) {
            $isInherited = $createdAt->lt($currentShift->start);
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
            'createdBy' => optional($occurrence->user)->name ?? 'Sistema',
            'is_inherited' => $isInherited,
            'origin' => $isInherited ? 'Herdada' : 'Atual',
            'is_open' => !$this->isClosedStatus((string) $occurrence->status),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $currentShift = \App\Models\Shift::where('user_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->first();

        $isSupervisor = strtolower((string) $request->user()->role) === 'supervisor';

        if (!$currentShift && !$isSupervisor) {
            return response()->json([]);
        }

        $occurrencesQuery = \App\Models\Occurrence::with(['shift', 'user']);

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
            ->orderBy('created_at', 'desc')
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
            ]);

            $currentShift = Shift::where('user_id', $request->user()->id)
                ->where('status', 'in_progress')
                ->first();

            $occurrenceId = $request->id ?? 'OC-' . date('Ym') . '-' . rand(1000, 9999);

            $shiftInfo = $currentShift ? " no turno " . $currentShift->id : "";
            
            $normalizedPayload = $this->normalizeOccurrencePayload($validated);

            $comments = $normalizedPayload['comments'] ?? [];
            $comments[] = [
                'id' => 'sys-' . uniqid(),
                'author' => 'Sistema',
                'text' => "Ocorrência criada pelo operador " . $request->user()->name . $shiftInfo,
                'type' => 'Sistema',
                'createdAt' => now()->toISOString()
            ];

            $occurrence = Occurrence::create([
                'id' => $occurrenceId,
                'user_id' => $request->user()->id,
                'shift_id' => $currentShift ? $currentShift->id : null,
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

            $occurrence->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Occurrence registered successfully.',
                'data' => $this->mapOccurrenceForFrontend($occurrence, $currentShift)
            ], 201);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function show($id): JsonResponse
    {
        $occurrence = Occurrence::with(['user', 'shift'])->findOrFail($id);
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

            $occurrence = Occurrence::with(['user', 'shift'])->findOrFail($id);
            $payload = $this->normalizeOccurrencePayload($validated);

            if (!empty($payload)) {
                $occurrence->update($payload);
            }

            $occurrence->refresh();
            $occurrence->load(['user', 'shift']);

            return response()->json([
                'success' => true,
                'data' => $this->mapOccurrenceForFrontend($occurrence, $occurrence->shift)
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
                    'status' => $data['status'] ?? 'open',
                    'description' => $data['description'] ?? '', 
                    'location' => $data['location'] ?? null,
                    'link_type' => $data['linkType'] ?? ($data['link_type'] ?? null),
                    'link_value' => $data['linkValue'] ?? ($data['link_value'] ?? null),
                    'comments' => $commentsHist,
                    'reminders' => $data['reminders'] ?? null,
                ]);

                $newOcc->load('user');
                $createdOccurrences[] = $this->mapOccurrenceForFrontend($newOcc, $currentShift);

                \Illuminate\Support\Facades\DB::table('occurrences')
                    ->where('id', $newId)
                    ->update(['created_at' => (clone $currentShift->start)->subMinutes(5)]);

                if ($oldId) {
                    Occurrence::where('id', $oldId)->update(['status' => 'resolved']);
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
