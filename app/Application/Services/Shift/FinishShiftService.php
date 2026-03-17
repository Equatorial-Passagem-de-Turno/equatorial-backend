<?php

namespace App\Application\Services\Shift;

use App\Models\Shift;
use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class FinishShiftService
{
    public function execute(int $userId, string $briefing, ?string $nextOperator, array $resolvedOccurrences)
    {
        DB::beginTransaction();

        try {
            $shift = Shift::where('user_id', $userId)
                ->where('status', 'in_progress')
                ->firstOrFail();

            $user = User::find($userId);
            $operatorName = $user ? $user->name : 'Operador';

            $nextOpId = null;
            if ($nextOperator && is_numeric($nextOperator)) {
                $nextOpId = (int) $nextOperator;
            }

            $shift->update([
                'briefing'         => $briefing,
                'end'              => now(),
                'status'           => 'finished',
                'next_operator_id' => $nextOpId
            ]);

            $occurrences = Occurrence::where('shift_id', $shift->id)->get();

            foreach ($occurrences as $occurrence) {
                $isResolvedNow = in_array($occurrence->id, $resolvedOccurrences);
                
                $isAlreadyResolved = in_array(strtolower($occurrence->status), ['resolved', 'finished', 'resolvida', 'finalizada']);

                $text = null;

                if ($isResolvedNow) {
                    $text = "Ocorrência resolvida pelo operador {$operatorName} no turno {$shift->id}";
                    $occurrence->status = 'resolved';
                } elseif (!$isAlreadyResolved) {
                    $text = "Ocorrência deixada pelo operador {$operatorName} do turno {$shift->id} para o próximo turno";
                }

                if ($text) {
                    $comments = $occurrence->comments ?? [];
                    $comments[] = [
                        'id' => 'sys-' . uniqid(),
                        'author' => 'Sistema',
                        'text' => $text,
                        'type' => 'Sistema',
                        'createdAt' => now()->toISOString()
                    ];

                    $occurrence->comments = $comments;
                    $occurrence->save();
                }
            }

            DB::commit();
            return $shift;

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception("Erro interno ao finalizar: " . $e->getMessage());
        }
    }
}