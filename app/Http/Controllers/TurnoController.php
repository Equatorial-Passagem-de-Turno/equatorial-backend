<?php

namespace App\Http\Controllers;

use App\Application\Services\Turno\IniciarTurnoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;
use App\Application\Services\Turno\EncerrarTurnoService;

class TurnoController extends Controller
{
    public function iniciar(
        Request $request,
        IniciarTurnoService $service
    ): JsonResponse {
        try {
            $turno = $service->executar(
                operadorId: $request->user()->id
            );

            return response()->json([
                'id' => $turno->id,
                'status' => $turno->status->value,
                'inicio' => $turno->inicio->format('Y-m-d H:i:s'),
            ], 201);

        } catch (DomainException $e) {
            return response()->json([
                'erro' => $e->getMessage()
            ], 400);
        }
    }

    public function encerrar(Request $request, EncerrarTurnoService $encerrarTurnoService)
    {
        try {
            $usuarioId = $request->user()->id; 
            
            $turno = $encerrarTurnoService->execute($usuarioId);

            return response()->json([
                'success' => true,
                'message' => 'Turno encerrado com sucesso.',
                'data' => $turno
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
