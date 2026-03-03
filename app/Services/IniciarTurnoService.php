<?php

namespace App\Services;

use App\Models\Turno;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Exception;

class IniciarTurnoService
{
    public function executar(int $usuarioId): Turno
    {
        return DB::transaction(function () use ($usuarioId) {

            $usuario = Usuario::findOrFail($usuarioId);

            $turnoAberto = Turno::where('usuario_id', $usuarioId)
                ->where('status', 'aberto')
                ->exists();

            if ($turnoAberto) {
                throw new Exception('Usuário já possui turno aberto.');
            }

            return Turno::create([
                'usuario_id' => $usuarioId,
                'inicio' => now(),
                'status' => 'aberto'
            ]);
        });
    }
}
