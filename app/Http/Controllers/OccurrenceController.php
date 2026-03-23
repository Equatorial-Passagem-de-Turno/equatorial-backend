<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class OccurrenceController extends Controller
{
    private function isClosedStatus(string $status): bool
    {
        $normalized = strtolower(trim($status));
        return in_array($normalized, ['resolved', 'finished', 'resolvida', 'finalizada', 'cancelada', 'fechada', 'encerrada'], true);
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

        if (!$currentShift) {
            return response()->json([]);
        }

        $occurrences = \App\Models\Occurrence::with('shift')
            ->whereHas('shift', function ($query) use ($currentShift) {
                $query->where('operation_desk_id', $currentShift->operation_desk_id);
            })
            ->where(function($query) {
                // Condição 1: Pega TUDO que NÃO está finalizado (nunca perde uma pendência)
                $query->whereNotIn('status', ['resolved', 'finished', 'resolvida', 'finalizada', 'Resolvida', 'Finalizada'])
                      // Condição 2: OU pega o que está finalizado, mas com limite de 15 dias para não travar a tela
                      ->orWhere('created_at', '>=', now()->subDays(15));
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $filteredOccurrences = $occurrences->filter(function ($occ) {
            $isClosed = in_array(strtolower($occ->status), ['resolved', 'finished', 'resolvida', 'finalizada']);
            
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
            $currentShift = Shift::where('user_id', $request->user()->id)
                ->where('status', 'in_progress')
                ->first();

            $occurrenceId = $request->id ?? 'OC-' . date('Ym') . '-' . rand(1000, 9999);

            $shiftInfo = $currentShift ? " no turno " . $currentShift->id : "";
            
            $comments = $request->comments ?? [];
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
                'title' => $request->title,
                'category' => $request->category,
                'priority' => $request->priority,
                'status' => $request->status,
                'description' => $request->description,
                'location' => $request->location,
                'link_type' => $request->linkType,
                'link_value' => $request->linkValue,
                'attachments' => $request->attachments,
                'comments' => $comments,
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
            $occurrence = Occurrence::with(['user', 'shift'])->findOrFail($id);
            $occurrence->update($request->all());
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
            $occurrences = $request->input('occurrences');
            $userId = $request->user()->id;

            $currentShift = \App\Models\Shift::where('user_id', $userId)
                                ->where('status', 'in_progress')
                                ->first();

            if ($currentShift) {
                $currentShift->update(['handover_acknowledged' => true]);
            }

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
                    'shift_id' => $currentShift ? $currentShift->id : null,
                    'title' => $data['title'] ?? 'Sem Título',
                    'category' => $data['category'] ?? 'Herdada de Turno',
                    'priority' => $data['priority'] ?? 'medium',
                    'status' => $data['status'] ?? 'open',
                    'description' => $data['description'] ?? '', 
                    'location' => $data['location'] ?? null,
                    'link_type' => $data['linkType'] ?? null,
                    'link_value' => $data['linkValue'] ?? null,
                    'comments' => $commentsHist,
                ]);

                $newOcc->load('user');
                $createdOccurrences[] = $this->mapOccurrenceForFrontend($newOcc, $currentShift);

                if ($currentShift) {
                    \Illuminate\Support\Facades\DB::table('occurrences')
                        ->where('id', $newId)
                        ->update(['created_at' => (clone $currentShift->start)->subMinutes(5)]);
                }

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
