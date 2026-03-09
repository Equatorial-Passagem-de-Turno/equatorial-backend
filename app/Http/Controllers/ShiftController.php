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
    /**
     * Starts a new shift for the authenticated user.
     */
    public function start(
        Request $request,
        StartShiftService $service
    ): JsonResponse {
        try {
            // O ID do usuário é obtido diretamente do token de autenticação
            $shift = $service->execute(
                userId: $request->user()->id
            );

            return response()->json([
                'id' => $shift->id,
                'status' => $shift->status->value,
                'start' => $shift->start->format('Y-m-d H:i:s'),
                'voltage_level' => $shift->voltageLevel->value,
            ], 201);

        } catch (DomainException | Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Finishes the current shift for the authenticated user.
     */
    public function finish(
        Request $request,
        FinishShiftService $service
    ): JsonResponse {
        $request->validate([
            'briefing' => 'required|string|min:10' // Exige pelo menos 10 caracteres
        ]);
        try {
            $userId = $request->user()->id;

            // O briefing é enviado no corpo da requisição (POST)
            $shift = $service->execute(
                $userId,
                $request->input('briefing')
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
}
