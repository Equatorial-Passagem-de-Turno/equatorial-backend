<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCircuitSwitchingRequest;
use App\Http\Requests\UpdateCircuitSwitchingRequest;
use App\Models\CircuitSwitching;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CircuitSwitchingController extends Controller
{
    public function index(): JsonResponse
    {
        $circuits = CircuitSwitching::with(['user:id,name'])
            ->where('status', 'manobrado')
            ->orderBy('deadline', 'asc')
            ->get();

        return response()->json($circuits);
    }

    public function store(StoreCircuitSwitchingRequest $request): JsonResponse
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

        $circuit = CircuitSwitching::create($data);

        return response()->json([
            'message' => 'Circuito manobrado registrado com sucesso.',
            'data' => $circuit
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $circuit = CircuitSwitching::with(['user:id,name'])->findOrFail($id);
        return response()->json($circuit);
    }

    public function update(UpdateCircuitSwitchingRequest $request, $id): JsonResponse
    {
        $circuit = CircuitSwitching::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('attachments')) {
            $attachmentPaths = $circuit->attachments ?? [];

            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('occurrences/attachments', 'public');
            }
            $data['attachments'] = $attachmentPaths;
        }

        if (isset($data['new_deadline']) && $data['new_deadline'] != $circuit->deadline) {

        }

        $circuit->update($data);

        return response()->json([
            'message' => 'Circuito atualizado com sucesso.',
            'data' => $circuit
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $circuit = CircuitSwitching::findOrFail($id);

        if (!empty($circuit->attachments)) {
            foreach ($circuit->attachments as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        $circuit->delete();

        return response()->json(['message' => 'Registro excluído com sucesso.']);
    }
}
