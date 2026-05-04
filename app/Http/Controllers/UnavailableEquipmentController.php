<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnavailableEquipmentRequest;
use App\Http\Requests\UpdateUnavailableEquipmentRequest;
use App\Models\UnavailableEquipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UnavailableEquipmentController extends Controller
{
    public function index(): JsonResponse
    {
        $equipments = UnavailableEquipment::with(['user:id,name'])
            ->where('status', 'indisponivel')
            ->orderBy('deadline', 'asc')
            ->get();

        return response()->json($equipments);
    }

    public function store(StoreUnavailableEquipmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('occurrences/attachments', 'public');
            }
        }
        $data['attachments'] = $attachmentPaths;

        $equipment = UnavailableEquipment::create($data);

        return response()->json([
            'message' => 'Equipamento indisponível registrado com sucesso.',
            'data' => $equipment
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $equipment = UnavailableEquipment::with(['user:id,name'])->findOrFail($id);

        // Aqui você pode adicionar lógica para retornar URLs completas dos anexos, se necessário

        return response()->json($equipment);
    }

    public function update(UpdateUnavailableEquipmentRequest $request, $id): JsonResponse
    {
        $equipment = UnavailableEquipment::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('attachments')) {
            $attachmentPaths = $equipment->attachments ?? [];

            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('occurrences/attachments', 'public');
            }
            $data['attachments'] = $attachmentPaths;
        }

        $equipment->update($data);

        return response()->json([
            'message' => 'Equipamento atualizado com sucesso.',
            'data' => $equipment
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $equipment = UnavailableEquipment::findOrFail($id);

        if (!empty($equipment->attachments)) {
            foreach ($equipment->attachments as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        $equipment->delete();

        return response()->json(['message' => 'Registro excluído com sucesso.']);
    }
}
