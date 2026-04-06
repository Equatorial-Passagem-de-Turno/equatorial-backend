<?php

namespace App\Http\Controllers;

use App\Models\OperationDesk;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class OperationDeskController extends Controller
{
    public function index(): JsonResponse
    {
        $desks = OperationDesk::where('is_active', true)->get(['id', 'code', 'name', 'location']);
        
        return response()->json($desks, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('operation_desks', 'name')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $desk = OperationDesk::create([
            'code' => $this->generateNextCode(),
            'name' => $validated['name'],
            'location' => $validated['location'] ?? 'COI',
            'is_active' => true,
        ]);

        return response()->json($desk->only(['id', 'code', 'name', 'location']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $desk = OperationDesk::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('operation_desks', 'name')
                    ->ignore($desk->id)
                    ->where(function ($query) {
                        $query->where('is_active', true);
                    }),
            ],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $desk->update([
            'name' => $validated['name'],
            'location' => $validated['location'] ?? $desk->location,
        ]);

        return response()->json($desk->only(['id', 'code', 'name', 'location']), 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $desk = OperationDesk::query()->findOrFail($id);
        $desk->update(['is_active' => false]);

        return response()->json([
            'message' => 'Mesa desativada com sucesso.',
        ], 200);
    }

    private function generateNextCode(): string
    {
        $counter = 1;
        do {
            $candidate = 'MESA-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $exists = OperationDesk::query()->where('code', $candidate)->exists();
            $counter++;
        } while ($exists);

        return $candidate;
    }
}
