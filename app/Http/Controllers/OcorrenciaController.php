<?php

namespace App\Http\Controllers;

use App\Application\Services\Ocorrencia\RegistrarOcorrenciaService;
use Illuminate\Http\Request;
use Exception;

class OcorrenciaController extends Controller
{
    public function store(Request $request, RegistrarOcorrenciaService $registrarOcorrenciaService)
    {
        try {
            $operadorId = $request->user()->id;
            
            $dados = $request->only(['titulo', 'descricao', 'tipo']);
            
            $ocorrencia = $registrarOcorrenciaService->execute($operadorId, $dados);

            return response()->json([
                'success' => true,
                'message' => 'Ocorrência registrada com sucesso.',
                'data' => $ocorrencia
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}