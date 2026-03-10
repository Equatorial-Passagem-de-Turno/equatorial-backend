<?php

namespace App\Http\Controllers;

use App\Application\Services\Occurrence\RegisterOccurrenceService;
use App\Models\Occurrence;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class OccurrenceController extends Controller
{
    public function index(): JsonResponse
    {
        $occurrences = Occurrence::orderBy('created_at', 'desc')->get();
        return response()->json($occurrences);
    }

    public function store(Request $request)
    {
        try {
            $occurrenceId = $request->id ?? 'OC-' . date('Ym') . '-' . rand(1000, 9999);

            $occurrence = Occurrence::create([
                'id' => $occurrenceId,
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'category' => $request->category,
                'priority' => $request->priority,
                'status' => $request->status,
                'description' => $request->description,
                'location' => $request->location, 
                'link_type' => $request->linkType,
                'link_value' => $request->linkValue,
                'attachments' => $request->attachments, 
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Occurrence registered successfully.',
                'data' => $occurrence
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show($id): JsonResponse
    {
        $occurrence = Occurrence::findOrFail($id);
        return response()->json($occurrence);
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $occurrence = Occurrence::findOrFail($id);
            $occurrence->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $occurrence
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

            foreach ($occurrences as $data) {
                Occurrence::create(array_merge($data, [
                    'user_id' => $userId,
                    'description' => $data['description'] . "\n(Herdada do turno anterior)"
                ]));
            }

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}