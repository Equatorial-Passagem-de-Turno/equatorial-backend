<?php

namespace App\Http\Controllers;

use App\Application\Services\Occurrence\RegisterOccurrenceService;
use App\Models\Occurrence; // Importe o seu Model aqui
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class OccurrenceController extends Controller
{
    // 1. READ (Listar Todas)
    public function index(): JsonResponse
    {
        // Trazendo todas do banco. Ajuste o 'with' se tiver relacionamentos (ex: 'location')
        $occurrences = Occurrence::orderBy('created_at', 'desc')->get();
        return response()->json($occurrences);
    }

    // 2. CREATE (Sua função store original mantida)
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
                'location' => $request->location, // O Laravel salva como JSON automaticamente
                'link_type' => $request->linkType, // Mapeando do React para o Banco
                'link_value' => $request->linkValue, // Mapeando do React para o Banco
                'attachments' => $request->attachments, // O Laravel salva como JSON automaticamente
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

    // 3. READ SINGLE (Ler apenas uma)
    public function show($id): JsonResponse
    {
        $occurrence = Occurrence::findOrFail($id);
        return response()->json($occurrence);
    }

    // 4. UPDATE (Atualizar)
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

    // 5. DELETE (Excluir)
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
                // Criamos cada ocorrência vinculando ao novo usuário
                Occurrence::create(array_merge($data, [
                    'user_id' => $userId,
                    // Opcional: marcar que foi herdada
                    'description' => $data['description'] . "\n(Herdada do turno anterior)"
                ]));
            }

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}