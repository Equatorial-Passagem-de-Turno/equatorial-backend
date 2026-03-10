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
}