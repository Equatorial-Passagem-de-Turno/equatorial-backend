<?php

namespace App\Http\Controllers;

use App\Models\OperationDesk;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');

        $users = User::query()
            ->with('operationDesk:id,name')
            ->where('id', '!=', $request->user()->id)
            ->when(!$includeInactive, function ($query) {
                $query->where('active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'voltage_level', 'operation_desk_id', 'active']);

        $payload = $users->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'voltage_level' => $user->voltage_level,
                'operation_desk_id' => $user->operation_desk_id,
                'operation_desk_name' => $user->operationDesk?->name,
                'active' => (bool) $user->active,
            ];
        });

        return response()->json($payload, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $deskId = $this->resolveDeskId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['operador', 'supervisor'])],
            'voltage_level' => ['required', Rule::in(['BT', 'MT', 'AT'])],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password'] ?? 'password'),
            'role' => $validated['role'],
            'voltage_level' => $validated['voltage_level'],
            'active' => true,
            'operation_desk_id' => $deskId,
        ]);

        $user->load('operationDesk:id,name');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'voltage_level' => $user->voltage_level,
            'operation_desk_id' => $user->operation_desk_id,
            'operation_desk_name' => $user->operationDesk?->name,
            'active' => (bool) $user->active,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::query()->where('role', 'operador')->findOrFail($id);
        $deskId = $this->resolveDeskId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'voltage_level' => ['required', Rule::in(['BT', 'MT', 'AT'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->voltage_level = $validated['voltage_level'];
        if (array_key_exists('active', $validated)) {
            $user->active = $validated['active'];
        }
        $user->operation_desk_id = $deskId;
        $user->save();

        $user->load('operationDesk:id,name');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'voltage_level' => $user->voltage_level,
            'operation_desk_id' => $user->operation_desk_id,
            'operation_desk_name' => $user->operationDesk?->name,
            'active' => (bool) $user->active,
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::query()->where('role', 'operador')->findOrFail($id);

        DB::transaction(function () use ($user) {
            // Remove referencias de repasse para evitar bloqueio de FK.
            Shift::query()
                ->where('next_operator_id', $user->id)
                ->update(['next_operator_id' => null]);

            // Exclui turnos do operador; ocorrencias vinculadas ao turno sao removidas em cascata.
            Shift::query()
                ->where('user_id', $user->id)
                ->delete();

            $user->delete();
        });

        return response()->json([
            'message' => 'Operador removido com sucesso.',
        ], 200);
    }

    private function resolveDeskId(Request $request): ?int
    {
        $deskId = $request->input('operation_desk_id');
        if ($deskId !== null && $deskId !== '') {
            OperationDesk::query()->whereKey($deskId)->where('is_active', true)->firstOrFail();

            return (int) $deskId;
        }

        $deskName = trim((string) $request->input('operation_desk_name', ''));
        if ($deskName !== '') {
            $desk = OperationDesk::query()
                ->where('is_active', true)
                ->where('name', $deskName)
                ->first();

            if ($desk) {
                return $desk->id;
            }
        }

        return null;
    }
}
