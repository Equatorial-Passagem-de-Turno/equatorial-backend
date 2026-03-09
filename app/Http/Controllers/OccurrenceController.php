<?php

namespace App\Http\Controllers;

use App\Application\Services\Occurrence\RegisterOccurrenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class OccurrenceController extends Controller
{
    /**
     * Registers a new occurrence for the authenticated user's active shift.
     * * @param Request $request
     * @param RegisterOccurrenceService $service
     * @return JsonResponse
     */
    public function store(Request $request, RegisterOccurrenceService $service): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // Captura os dados usando o novo padrão de nomenclatura em inglês
            $data = $request->only(['title', 'description', 'type']);

            $occurrence = $service->execute($userId, $data);

            return response()->json([
                'success' => true,
                'message' => 'Occurrence registered successfully.',
                'data' => [
                    'id' => $occurrence->id,
                    'shift_id' => $occurrence->shiftId,
                    'title' => $occurrence->title,
                    'type' => $occurrence->type->value,
                    'status' => $occurrence->status->value
                ]
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
